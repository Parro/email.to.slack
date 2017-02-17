<?php
namespace EmailToSlack;


use CL\Slack\Payload\ChannelsListPayload;
use CL\Slack\Payload\ChannelsListPayloadResponse;
use CL\Slack\Payload\ChatPostMessagePayloadResponse;
use CL\Slack\Payload\FilesUploadPayload;
use CL\Slack\Transport\ApiClient;
use Ddeboer\Imap\Connection;
use Ddeboer\Imap\Search\Flag\Unseen;
use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Server;
use Monolog\Logger;

class EmailToSlack
{
    /** @var  ApiClient */
    public $slackClient;

    /** @var  ApiClient */
    public $imapClient;

    /** @var  Connection */
    public $imapConnection;

    /** @var Logger */
    public $logger;

    public function __construct($slackToken, $imapHostname, $logger = null, $slackClient = null, $imapClient = null)
    {
        if (!is_null($logger)) {
            $this->logger = $logger;
        } else {
            $this->logger = new Logger('emailToSlack');
        }

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

    public function checkSlackChannelExists($slackChannels, $channel, $message)
    {
        if (in_array($channel, $slackChannels)) {
            return true;
        }

        $this->logger->warning('No channel named "' . $channel . '" found in this team', ['channel' => $channel, 'subject' => $message->getSubject(), 'time' => $message->getDate()->format('Y-m-d H:i:s')]);

        // Mark message as seen
        $message->getBodyHtml();

        return false;
    }

    public function checkMail($mailUsername, $mailPassword)
    {
        $postSent = 0;

        $messages = $this->getMails($mailUsername, $mailPassword);

        if (!empty($messages)) {
            $slackChannels = $this->getSlackChannels();

            foreach ($messages as $message) {
//            $message->keepUnseen();
                $channelMail = $this->getChannelFromEmail($message);

                if ($channelMail !== false && $this->checkSlackChannelExists($slackChannels, $channelMail, $message)) {
                    $this->postEmailToSlackChannel($message, $channelMail);

                    $postSent ++;
                }
            }
        }

        return $postSent;
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

        $this->logger->warning('No channel found', ['channel' => '', 'subject' => $message->getSubject(), 'time' => $message->getDate()->format('Y-m-d H:i:s')]);

        // Mark message as seen
        $message->getBodyHtml();

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
            $this->logger->info('Message posted in channel "' . $channelMail . '" ', ['channel' => $channelMail, 'subject' => $message->getSubject(), 'time' => $message->getDate()->format('Y-m-d H:i:s')]);
        } else {
            throw new \Exception($response->getErrorExplanation());
        }
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

        if (count($fromPreg) > 0) {
            $from = $fromPreg[1];
            $from = str_replace(["\r", "\n"], "", $from);
        } else {
            $from = $message->getFrom();
        }


        preg_match('/A:(.*)|To:(.*)/', $messageBody, $toPreg);

        if (count($toPreg) > 0) {
            $to = $toPreg[1];
            $to = str_replace(["\r", "\n"], "", $to);
        } else {
            $messageToArr = [];
            foreach ($message->getTo() as $messageTo) {
                $messageToArr[] = $messageTo;
            }

            $to = implode(', ', $messageToArr);
        }

        $text = '';

        $text .= '*From: ' . trim($from) . '*' . "\n";

        $text .= '*To: ' . trim($to) . '*' . "\n";

        if ($message->hasAttachments()) {
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