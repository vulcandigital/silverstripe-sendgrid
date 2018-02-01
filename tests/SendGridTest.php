<?php

namespace Vulcan\SendGrid\Tests;

use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\ArrayData;
use Vulcan\SendGrid\SendGrid;

class SendGridTest extends FunctionalTest
{
    protected $originalKey;

    /**
     * @var SendGrid
     */
    protected $sendGrid;

    public function setUp()
    {
        parent::setUp();

        $this->originalKey = SendGrid::config()->get('api_key');

        SendGrid::config()->set('api_key', 'XXXX-XXXX-XXXX-XXXX');

        $this->sendGrid = SendGrid::create();
    }

    public function tearDown()
    {
        parent::tearDown();

        SendGrid::config()->set('api_key', $this->originalKey);
    }

    public function testSendGrid()
    {
        $dbHtmlText = DBHTMLText::create("<p>Hello World</p>");
        $this->sendGrid->setBody($dbHtmlText);
        $this->assertEquals("<p>Hello World</p>", $this->sendGrid->getBody(), 'When setBody is passed a DBHTMLText object, it should automatically convert that object to string');

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
}