<?php

namespace Vulcan\SendGrid\Tests;

use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\ArrayData;
use Vulcan\SendGrid\SendGrid;

/**
 * Class SendGridTest
 * @package Vulcan\SendGrid\Tests
 * @covers  \Vulcan\SendGrid\SendGrid
 */
class SendGridTest extends FunctionalTest
{
    protected $originalKey;

    /**
     * @var SendGrid
     */
    protected $sendGrid;

    protected $hasApiKey = false;

    public function setUp()
    {
        parent::setUp();

        $this->originalKey = SendGrid::config()->get('api_key');

        $apiKey = getenv('SG_API_KEY');

        SendGrid::config()->set('api_key', $apiKey ?: 'XXXX-XXXX-XXXX-XXXX');
        $this->sendGrid = SendGrid::create();

        if ($apiKey) {
            $this->hasApiKey = true;
        }
    }

    public function tearDown()
    {
        parent::tearDown();

        SendGrid::config()->set('api_key', $this->originalKey);
    }

    /**
     * @covers \Vulcan\SendGrid\SendGrid::setBody()
     * @covers \Vulcan\SendGrid\SendGrid::getBody()
     */
    public function testBody()
    {
        $dbHtmlText = DBHTMLText::create()->setValue("<p>Hello World</p>");
        $this->sendGrid->setBody($dbHtmlText);
        $this->assertEquals("<p>Hello World</p>", $this->sendGrid->getBody(), 'When setBody is passed a DBHTMLText object, it should automatically convert that object to string');
    }

    /**
     * @covers \Vulcan\SendGrid\SendGrid::addRecipient()
     * @covers \Vulcan\SendGrid\SendGrid::getRecipients()
     */
    public function testRecipients()
    {
        $this->sendGrid->addRecipient('reece@vulcandigital.co.nz', 'Reece Alexander', [':salutation' => 'Mr']);

        /** @var ArrayData $recipient */
        $recipient = $this->sendGrid->getRecipients()->first();
        $this->assertEquals([
            'Email'            => 'reece@vulcandigital.co.nz',
            'Name'             => 'Reece Alexander',
            'Personalizations' => [
                [
                    'Key'   => ':salutation',
                    'Value' => 'Mr'
                ]
            ]
        ], $recipient->toMap());

        try {
            $this->sendGrid->addRecipient('some.bogus.email');
            $this->fail('An exception was meant to be thrown when an invalid email is added as a recipient');
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * @covers \Vulcan\SendGrid\SendGrid::setScheduleTo()
     * @covers \Vulcan\SendGrid\SendGrid::getSchedule()
     */
    public function testScheduling()
    {
        try {
            $this->sendGrid->setScheduleTo(strtotime('-1 year'));
            $this->fail('You should not be able to schedule a date in the past');
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }

        $dbDateTime = DBDatetime::create()->setValue(date('Y-m-d', strtotime('+1 day', DBDatetime::now()->getTimestamp())));
        $this->sendGrid->setScheduleTo($dbDateTime);
        $this->assertEquals($dbDateTime->getTimestamp(), $this->sendGrid->getSchedule(), 'DBDatetime object was not correctly parsed');
    }

    /**
     * @covers \Vulcan\SendGrid\SendGrid::send()
     * @covers \Vulcan\SendGrid\SendGrid::setSandboxMode()
     * @covers \Vulcan\SendGrid\SendGrid::setTemplateId()
     * @covers \Vulcan\SendGrid\SendGrid::setFrom()
     * @covers \Vulcan\SendGrid\SendGrid::addCustomArg()
     */
    public function testSend()
    {
        if (!$this->hasApiKey) {
            return;
        }

        $this->sendGrid = SendGrid::create();
        $this->sendGrid->setSandboxMode(true);
        $this->sendGrid->setFrom('noreply@vulcandigital.co.nz');
        $this->sendGrid->addRecipient('reece@vulcandigital.co.nz', 'Reece Alexander', [
            ':title'        => 'Hello there',
            ':button_title' => 'Login',
            ':button_link'  => 'https://example.com/login'
        ]);
        $this->sendGrid->setSubject('Just testing...');
        $this->sendGrid->setBody('<p>Lorem ipsum dolor sit amet.</p>');
        $this->sendGrid->setTemplateId(getenv('SG_TEMPLATE_ID'));
        $this->sendGrid->addCustomArg(':year', DBDatetime::now()->Year());

        $this->assertTrue($this->sendGrid->send());
    }

    /**
     * @covers \Vulcan\SendGrid\SendGrid::addCustomArg()
     * @covers \Vulcan\SendGrid\SendGrid::getCustomArgs()
     */
    public function testCustomArgs()
    {
        $this->sendGrid->addCustomArg(':name', 'Reece Alexander');

        /** @var ArrayData $first */
        $first = $this->sendGrid->getCustomArgs()->first();

        $this->assertEquals([
            'Key'   => ':name',
            'Value' => 'Reece Alexander'
        ], $first->toMap());

        try {
            $this->sendGrid->addCustomArg(':name', 'Reece Alexander');
            $this->fail('You should not be able to add the same customArg key twice');
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * @covers \Vulcan\SendGrid\SendGrid::getErrorMap()
     */
    public function testErrorMap()
    {
        $this->assertInstanceOf(ArrayList::class, $this->sendGrid->getErrorMap());
    }

    /**
     * @covers \Vulcan\SendGrid\SendGrid::getErrorDefinition()
     */
    public function testErrorDefinition()
    {
        $definition = $this->sendGrid->getErrorDefinition(200);

        $this->assertEquals('OK', $definition->Message);
    }

    /**
     * @covers \Vulcan\SendGrid\SendGrid::isSandbox()
     * @covers \Vulcan\SendGrid\SendGrid::setSandboxMode()
     */
    public function testSandboxMode()
    {
        $this->sendGrid->setSandboxMode(true);
        $this->assertTrue($this->sendGrid->isSandbox());
    }

    /**
     * @covers \Vulcan\SendGrid\SendGrid::setTemplateId()
     * @covers \Vulcan\SendGrid\SendGrid::getTemplateId()
     */
    public function testTemplateId()
    {
        $id = '1234-5678-9123-4567';
        $this->sendGrid->setTemplateId($id);
        $this->assertEquals($id, $this->sendGrid->getTemplateId());
    }

    /**
     * @covers \Vulcan\SendGrid\SendGrid::getApiKey()
     */
    public function testGetApiKey()
    {
        if ($this->hasApiKey) {
            $this->assertEquals(getenv('SG_API_KEY'), $this->sendGrid->getApiKey());
            return;
        }

        $this->assertEquals('XXXX-XXXX-XXXX-XXXX', $this->sendGrid->getApiKey());
    }

    /**
     * @covers \Vulcan\SendGrid\SendGrid::setReplyTo()
     * @covers \Vulcan\SendGrid\SendGrid::getReplyTo()
     */
    public function testReplyTo()
    {
        $this->sendGrid->setReplyTo('john@doe.com');
        $this->assertEquals('john@doe.com', $this->sendGrid->getReplyTo());
    }

    /**
     * @covers \Vulcan\SendGrid\SendGrid::setFrom()
     * @covers \Vulcan\SendGrid\SendGrid::setFromName()
     * @covers \Vulcan\SendGrid\SendGrid::getFrom()
     * @covers \Vulcan\SendGrid\SendGrid::getFromName()
     */
    public function testFrom()
    {
        $this->sendGrid->setFrom('jane@doe.com');
        $this->sendGrid->setFromName('Jane Doe');

        $this->assertEquals('jane@doe.com', $this->sendGrid->getFrom());
        $this->assertEquals('Jane Doe', $this->sendGrid->getFromName());
    }

    /**
     * @covers \Vulcan\SendGrid\SendGrid::setSubject()
     * @covers \Vulcan\SendGrid\SendGrid::getSubject()
     */
    public function testSubject()
    {
        $subject = 'Where have you been John!';
        $this->sendGrid->setSubject($subject);

        $this->assertEquals($subject, $this->sendGrid->getSubject());
    }
}
