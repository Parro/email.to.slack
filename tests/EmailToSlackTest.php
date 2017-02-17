<?php
namespace EmailToSlack\tests;


use Ddeboer\Imap\Message;
use Ddeboer\Imap\MessageIterator;
use EmailToSlack\EmailToSlack;


class EmailToSlackTest extends AbstractTest
{
    public function testCheckMail()
    {
        $this->initEmailToSlack();

        $this->emailToSlack->logger->shouldReceive('info');

        $postSent = $this->emailToSlack->checkMail('', '');

        $postSentExpected = 2;

        $this->assertEquals($postSentExpected, $postSent);

    }

    public function testGetMails()
    {
        $this->initEmailToSlack();

//        $messagesExpected = false;

//        $this->getMessagesMock();

        $messages = $this->emailToSlack->getMails('', '');

        $this->assertInstanceOf(Message::class, $messages[0]);

        $this->assertEquals('Mail di test per general', $messages[0]->getSubject());
        $this->assertContains('slack-general@mmh-tech.com', $messages[0]->getTo());
        $this->assertEquals('Mail di test per random', $messages[1]->getSubject());
        $this->assertContains('slack-random@mmh-tech.com', $messages[1]->getTo());

    }

    public function testGetChannelFromEmail()
    {
        $this->initEmailToSlack();

        $message = $this->getMessageMock('forward');

        $channel = $this->emailToSlack->getChannelFromEmail($message);

        $channelExpected = 'general';

        $this->assertEquals($channel, $channelExpected);

        $messageCc = $this->getMessageMock('forward','random', false, true);

        $channelCc = $this->emailToSlack->getChannelFromEmail($messageCc);

        $channelExpectedCc = 'random';

        $this->assertEquals($channelCc, $channelExpectedCc);
    }

    public function testGetSlackChannels()
    {
        $this->initEmailToSlack();

        $channels = $this->emailToSlack->getSlackChannels();

        $channelsExpected = ['general', 'random'];

        $this->assertEquals($channels, $channelsExpected);
    }

    public function testCheckSlackChannelExists()
    {
        $this->initEmailToSlack();

        $messageMock = $this->getMessageMock('forward');

        $channels = ['general', 'random'];

        $channelExist = 'general';

        $this->emailToSlack->logger->shouldReceive('warning')->with('No channel named "' . $channelExist . '" found in this team', ['channel' => $channelExist, 'subject' => 'Mail di test per general', 'time' => '2017-02-13 18:01:11']);

        $expected = $this->emailToSlack->checkSlackChannelExists($channels, $channelExist, $messageMock);


        $this->assertTrue($expected);

        $channelNotExist = 'cips';

        $this->emailToSlack->logger->shouldReceive('warning')->with('No channel named "' . $channelNotExist . '" found in this team', ['channel' => $channelNotExist, 'subject' => 'Mail di test per general', 'time' => '2017-02-13 18:01:11']);

        $expected = $this->emailToSlack->checkSlackChannelExists($channels, $channelNotExist, $messageMock);

        $this->assertFalse($expected);
    }
}
