<?php
namespace EmailToSlack;


use CL\Slack\Payload\ChannelsListPayload;
use CL\Slack\Payload\ChannelsListPayloadResponse;
use CL\Slack\Payload\ChatPostMessagePayload;
use CL\Slack\Payload\ChatPostMessagePayloadResponse;
use CL\Slack\Payload\FilesUploadPayload;
use CL\Slack\Transport\ApiClient;
use Ddeboer\Imap\Connection;
use Ddeboer\Imap\Search\Flag\Unseen;
use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Server;

class EmailToSlack
{
    /** @var  ApiClient */
    public $slackClient;

    /** @var  ApiClient */
    public $imapClient;

    /** @var  Connection */
    public $imapConnection;

    public function __construct($slackToken, $imapHostname, $slackClient = null, $imapClient = null)
    {
        if (!is_null($slackClient)) {
            $this->slackClient = $slackClient;
        } else {
            $this->slackClient = new ApiClient($slackToken);
        }
        if (!is_null($imapClient)) {
            $this->imapClient = $imapClient;
        } else {
            $this->imapClient = new Server($imapHostname, 143, '');
        }
    }

    public function getSlackChannels()
    {
        $channelList = [];

        $payload = new ChannelsListPayload();

        /** @var ChannelsListPayloadResponse $response */
        $response = $this->slackClient->send($payload);

        if ($response->isOk()) {
            foreach ($response->getChannels() as $channel) {
                $channelList[] = $channel->getName();
            }
        } else {
            throw new \Exception($response->getErrorExplanation());
        }

        return $channelList;
    }

    public function checkSlackChannelExists($slackChannels, $channel)
    {
        if (in_array($channel, $slackChannels)) {
            return true;
        }

        throw new \Exception('There is no channel "' . $channel . '"" in this Slack team');
    }

    public function checkMail($mailUsername, $mailPassword)
    {
        $messages = $this->getMails($mailUsername, $mailPassword);

        if(!empty($messages)) {
            $slackChannels = $this->getSlackChannels();

            foreach ($messages as $message) {
//            $message->keepUnseen();
                $channelMail = $this->getChannelFromEmail($message);

                if($channelMail !== false) {
                    $this->checkSlackChannelExists($slackChannels, $channelMail);

                    $this->postEmailToSlackChannel($message, $channelMail);
                }
            }
        }
    }

    /**
     * @param string $mailUsername
     * @param string $mailPassword
     * @return \Ddeboer\Imap\Message[]|\Ddeboer\Imap\MessageIterator
     */
    public function getMails($mailUsername, $mailPassword)
    {
        $this->imapConnection = $this->imapClient->authenticate($mailUsername, $mailPassword);

        $mailbox = $this->imapConnection->getMailbox('INBOX');

        $search = new SearchExpression();
        $search->addCondition(new Unseen());

        $messages = $mailbox->getMessages($search);

        return $messages;

    }

    /**
     * @param \Ddeboer\Imap\Message $message
     * @return string|bool
     */
    public function getChannelFromEmail($message)
    {
        $mailTos = $message->getTo();

        foreach ($mailTos as $mailTo) {
            preg_match('/slack-(.*)@/', $mailTo, $found);

            if (count($found) > 0) {
                return $found[1];
            }
        }

        $mailCcs = $message->getCc();

        foreach ($mailCcs as $mailCc) {
            preg_match('/slack-(.*)@/', $mailCc, $found);

            if (count($found) > 0) {
                return $found[1];
            }
        }

        return false;
    }

    /**
     * @param \Ddeboer\Imap\Message $message
     * @param string $channelMail
     * @throws \Exception
     */
    public function postEmailToSlackChannel($message, $channelMail)
    {
        $payload = $this->getPayloadFile($message);

        $payload->addChannel($channelMail);

        /** @var ChatPostMessagePayloadResponse $response */
        $response = $this->slackClient->send($payload);

        if ($response->isOk()) {
            echo "Message sent";
        } else {
            throw new \Exception($response->getErrorExplanation());
        }
    }

    /**
     * @param \Ddeboer\Imap\Message $message
     * @return ChatPostMessagePayload
     * @deprecated
     */
    public function getPayloadPost($message)
    {
        $payload = new ChatPostMessagePayload();

        $text = '';

        $text .= '*From: ' . $message->getFrom() . '*' . "\n";

        $messageToArr = [];
        foreach ($message->getTo() as $messageTo) {
            $messageToArr[] = $messageTo;
        }


        $text .= '*To: ' . implode(', ', $messageToArr) . '*' . "\n";

        $messageBody = $message->getBodyText();

        if (is_null($messageBody)) {
            $messageBody = $message->getBodyHtml();
        }

        $messageBody = preg_replace('/<(br[^>]*)>/i', "\n", $messageBody);

        $text .= html_entity_decode(strip_tags($messageBody));


        $payload->setText($text);

        return $payload;
    }

    /**
     * @param \Ddeboer\Imap\Message $message
     * @return FilesUploadPayload
     */
    public function getPayloadFile($message)
    {
        $payload = new FilesUploadPayload();

        $messageBody = $message->getBodyText();

        if (is_null($messageBody)) {
            $messageBody = $message->getBodyHtml();
        }

        preg_match('/Da:(.*)|From:(.*)/', $messageBody, $fromPreg);

        $from = $fromPreg[1];
        $from = str_replace(["\r", "\n"], "", $from);

        $messageToArr = [];
        foreach ($message->getTo() as $messageTo) {
            $messageToArr[] = $messageTo;
        }


        preg_match('/A:(.*)|To:(.*)/', $messageBody, $toPreg);

        $to = $toPreg[1];
        $to = str_replace(["\r", "\n"], "", $to);

        $text = '';

        $text .= '*From: ' . $from . '*' . "\n";

        $text .= '*To: ' . $to . '*' . "\n";

        if($message->hasAttachments()){
            $text .= '*With ' . count($message->getAttachments()) . ' attachments*' . "\n";
        }

        $this->imapClient;

        $messageBody = preg_replace('/<(br[^>]*)>/i', "\n", $messageBody);

        $text .= html_entity_decode(strip_tags($messageBody));

        $mailOriginal = imap_fetchbody($this->imapConnection->getResource(), $message->getNumber(), '');//, \FT_PEEK);

        $payload->setContent($mailOriginal);
        $payload->setFilename('mail-' . $message->getDate()->format('Y-m-d_H:i:s') . '.eml');

        $payload->setInitialComment($text);

        return $payload;
    }

}