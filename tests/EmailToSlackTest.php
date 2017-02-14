<?php
namespace EmailToSlack\tests;


use Ddeboer\Imap\Message;
use Ddeboer\Imap\MessageIterator;
use EmailToSlack\EmailToSlack;


class EmailToSlackTest extends AbstractTest
{

    public function testGetMails()
    {
        $this->initEmailToSlack();

//        $messagesExpected = false;

//        $this->getMessagesMock();

        $messages = $this->emailToSlack->getMails('','');

        $this->assertInstanceOf(Message::class, $messages[0]);

        $this->assertEquals('Mail di test per general', $messages[0]->getSubject());
        $this->assertContains('slack-general@mmh-tech.com', $messages[0]->getTo());
        $this->assertEquals('Mail di test per random', $messages[1]->getSubject());
        $this->assertContains('slack-random@mmh-tech.com', $messages[1]->getTo());

    }

    public function testGetChannelFromEmail()
    {
        $this->initEmailToSlack();

        $messages = $this->getMessagesMock();

        $channel = $this->emailToSlack->getChannelFromEmail($messages[0]);

        $channelExpected = 'general';

        $this->assertEquals($channel, $channelExpected);
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

        $channels = ['general', 'random'];

        $channelExist = 'general';

        $expected = $this->emailToSlack->checkSlackChannelExists($channels, $channelExist);

        $this->assertTrue($expected);

        $channelNotExist = 'cips';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('There is no channel "'. $channelNotExist.'"" in this Slack team');

        $this->emailToSlack->checkSlackChannelExists($channels, $channelNotExist);
    }
}
