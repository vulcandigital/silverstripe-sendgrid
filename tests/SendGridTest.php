<?php

namespace Vulcan\SendGrid\Tests;

use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\ArrayData;
use Vulcan\SendGrid\SendGrid;

/**
 * Class SendGridTest
 * @package Vulcan\SendGrid\Tests
 *
 * @covers SendGrid::
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

        if ($apiKey) {
            $this->hasApiKey = true;
        }

        $this->sendGrid = SendGrid::create();
    }

    public function tearDown()
    {
        parent::tearDown();

        SendGrid::config()->set('api_key', $this->originalKey);
    }

    /**
     * @covers SendGrid::setBody()
     * @covers SendGrid::getBody()
     */
    public function testBody()
    {
        $dbHtmlText = DBHTMLText::create()->setValue("<p>Hello World</p>");
        $this->sendGrid->setBody($dbHtmlText);
        $this->assertEquals("<p>Hello World</p>", $this->sendGrid->getBody(), 'When setBody is passed a DBHTMLText object, it should automatically convert that object to string');
    }

    /**
     * @covers SendGrid::addRecipient()
     * @covers SendGrid::getRecipients()
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
     * @covers SendGrid::setScheduleTo()
     * @covers SendGrid::getSchedule()
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
}
