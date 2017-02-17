<?php
namespace EmailToSlack;

use EmailToSlack\tests\AbstractTest;
use Mockery;

function imap_fetchbody($imap_stream, $msg_number, $section, $options = 0)
{
    return EmailToSlackPayloadTest::$functions->imap_fetchbody($imap_stream, $msg_number, $section, $options = 0);
}

class EmailToSlackPayloadTest extends AbstractTest
{
    public static $functions;

    public function setUp()
    {
        self::$functions = Mockery::mock();
    }

    public function testGetPayloadFileForward()
    {
        $this->initEmailToSlack();

        $messageType = 'forward';

        $messageMock = $this->getMessageMock($messageType);

        self::$functions->shouldReceive('imap_fetchbody')->with(1, 1, '', 0)->once()->andReturn(file_get_contents(__DIR__ . '/fixture/' . $messageType . '.eml'));

        $messages = $this->emailToSlack->getMails('', '');

        $payload = $this->emailToSlack->getPayloadFile($messageMock);

        $payloadExpectedFileName = 'mail-2017-02-13_18:01:11.eml';

        $this->assertEquals($payloadExpectedFileName, $payload->getFilename());

        $payloadInitialComment = $payload->getInitialComment();

        $payloadExpectedInitialCommentFrom = '/\*From: Giuseppe <giuseppe@example.com>\*/';

        $this->assertRegExp($payloadExpectedInitialCommentFrom, $payloadInitialComment);

        $payloadExpectedInitialCommentTo = '/\*To: Francesca <francesca@example.com>, Yan <yan@example.com>, Gabriele <gabriele@example.com>, Mauro <mauro@example.com>, Lorenzo <lorenzo@example.com>\*/';

        $this->assertRegExp($payloadExpectedInitialCommentTo, $payloadInitialComment);

    }

    public function testGetPayloadFileCC()
    {
        $this->initEmailToSlack();

        $messageType = 'cc';

        $messageMock = $this->getMessageMock($messageType);

        self::$functions->shouldReceive('imap_fetchbody')->with(1, 1, '', 0)->once()->andReturn(file_get_contents(__DIR__ . '/fixture/' . $messageType . '.eml'));

        $messages = $this->emailToSlack->getMails('', '');

        $payload = $this->emailToSlack->getPayloadFile($messageMock);

        $payloadExpectedFileName = 'mail-2017-02-13_18:01:11.eml';

        $this->assertEquals($payloadExpectedFileName, $payload->getFilename());

        $payloadInitialComment = $payload->getInitialComment();

        $payloadExpectedInitialCommentFrom = '/\*From: mauro@example.com\*/';

        $this->assertRegExp($payloadExpectedInitialCommentFrom, $payloadInitialComment);

        $payloadExpectedInitialCommentTo = '/\*To: slack-general@mmh-tech.com, test@example.com\*/';

        $this->assertRegExp($payloadExpectedInitialCommentTo, $payloadInitialComment);

    }

    public function testGetPayloadFileAttachments()
    {
        $this->initEmailToSlack();

        $messageType = 'forward';

        $messageMock = $this->getMessageMock($messageType, 'general', true, false, true);

        $messageMock->shouldReceive('getAttachments')->andReturn(['', '']);

        self::$functions->shouldReceive('imap_fetchbody')->with(1, 1, '', 0)->once()->andReturn(file_get_contents(__DIR__ . '/fixture/' . $messageType . '.eml'));

        $messages = $this->emailToSlack->getMails('', '');

        $payload = $this->emailToSlack->getPayloadFile($messageMock);

        $payloadInitialComment = $payload->getInitialComment();

        $payloadExpectedInitialCommentAttachments = '/\*With 2 attachments\*/';

        $this->assertRegExp($payloadExpectedInitialCommentAttachments, $payloadInitialComment);
    }
}