<?php

namespace Vulcan\SendGrid;

use SendGrid\Personalization;
use SendGrid\Response;
use SilverStripe\Assets\File;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\ArrayLib;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\ArrayData;
use Vulcan\SendGrid\Exceptions\SendGridException;

/**
 * Class SendGrid
 * @package Vulcan\SendGrid
 *
 * @author  Reece Alexander <reece@vulcandigital.co.nz>
 */
class SendGrid
{
    use Injectable, Configurable;

    /**
     * @config
     * @var bool
     */
    private static $api_key = false;

    /**
     * @var \SendGrid
     */
    protected $sendGrid;

    /**
     * @var ArrayList
     */
    protected $to;

    /**
     * @var string
     */
    protected $subject;

    /**
     * @var string
     */
    protected $from;

    /**
     * @var null|string
     */
    protected $fromName = null;

    /**
     * @var string
     */
    protected $replyTo;
    /**
     * @var string
     */
    protected $templateId;

    /**
     * @var string
     */
    protected $body;

    /**
     * @var ArrayList
     */
    protected $attachments;

    /**
     * SendGrid constructor.
     */
    public function __construct()
    {
        $this->to = ArrayList::create();
        $this->attachments = ArrayList::create();
        $this->sendGrid = new \SendGrid($this->getApiKey());
    }

    /**
     * Send the email
     *
     * @throws SendGridException
     */
    public function send()
    {
        $this->validate();

        $from = new \SendGrid\Email($this->getFromName(), $this->getFrom());
        $content = new \SendGrid\Content('text/html', $this->getBody());

        $first = new \SendGrid\Email($this->getRecipients()->first()->Name, $this->getRecipients()->first()->Email);
        $mail = new \SendGrid\Mail($from, $this->getSubject(), $first, $content);

        if ($this->getReplyTo()) {
            $mail->setReplyTo($this->getReplyTo());
        }

        $i = 0;
        foreach ($this->getRecipients() as $recipient) {
            if ($i == 0) {
                /** @var Personalization $personalization */
                $personalization = $mail->personalization[0];
            } else {
                $personalization = new \SendGrid\Personalization();
                $to = new \SendGrid\Email($recipient->Name, $recipient->Email);
                $personalization->addTo($to);
                $personalization->setSubject($this->getSubject());
            }

            foreach ($recipient->Personalizations as $map) {
                $personalization->addSubstitution($map['Key'], $map['Value']);
            }

            if ($i !== 0) {
                $mail->addPersonalization($personalization);
            }

            $i++;
        }

        foreach ($this->getAttachments() as $attachment) {
            $mail->addAttachment([
                'content'  => $attachment->Content,
                'type'     => $attachment->Type,
                'filename' => $attachment->Filename
            ]);
        }

        $mail->setTemplateId($this->getTemplateId());

        /** @var Response $response */
        $response = $this->sendGrid->client->mail()->send()->post($mail);

        // 2xx responses indicate success
        // 200 Your message is valid, but it is not queued to be delivered (sandbox only)
        // 202 Your message is both valid, and queued to be delivered.
        if (!in_array($response->statusCode(), [200, 202])) {
            $definition = $this->getErrorDefinition($response->statusCode());
            throw new SendGridException(sprintf('[Response: %s - %s] %s', $definition->Code, $definition->Message, $definition->Reason));
        }

        return true;
    }

    /**
     * Handles adding file attachments to the email
     *
     * @param File|string $file         The file object to attach to the email, or an absolute path to a file
     * @param null|string $filename     The name of the file, must include extension. Will default to current filename
     * @param bool        $forcePublish Only applicable if the provided file is a {@link File} object. If the provided file is unpublished,
     *                                  setting this to true will publish it forcibly, immediately
     *
     * @return $this
     */
    public function addAttachment($file, $filename = null, $forcePublish = false)
    {
        if ($file instanceof File) {
            return $this->addFileAsAttachment($file, $filename, $forcePublish);
        }

        if (!file_exists($file)) {
            throw new \InvalidArgumentException("That file [$file] does not exist");
        }

        if (!is_readable($file)) {
            throw new \InvalidArgumentException("That file [$file] exists, but is not readable");
        }

        $this->attachments->push([
            'Content'  => base64_encode(file_get_contents($file)),
            'Type'     => mime_content_type($file),
            'Filename' => ($filename) ? $filename : basename($file),
            'Size'     => filesize($file)
        ]);

        return $this;
    }

    /**
     * Handles adding {@link File} objects as attachments
     *
     * @param File $file
     * @param      $filename
     * @param      $forcePublish
     *
     * @return $this
     */
    private function addFileAsAttachment(File $file, $filename, $forcePublish)
    {
        if (!$file->isPublished()) {
            if (!$forcePublish) {
                throw new \InvalidArgumentException("That file [$file->Filename] is not published, and won't be visible to the recipient, please publish the image first or toggle the forcePublish parameter");
            }

            $file->publishSingle();
        }

        $path = Controller::join_links(Director::baseFolder(), $file->Link());

        if (!file_exists($path)) {
            throw new \InvalidArgumentException("That attachments represents a file that does not exist [$path]");
        }

        $contents = base64_encode(file_get_contents($path));

        $this->attachments->push([
            'Content'  => $contents,
            'Type'     => $file->getMimeType(),
            'Filename' => ($filename) ? $filename : basename($file->Filename),
            'Size'     => $file->getAbsoluteSize()
        ]);

        return $this;
    }

    /**
     * Validate that the object is ready to send an email
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    public function validate()
    {
        if (!$this->getFrom()) {
            throw new \InvalidArgumentException('You must set the from address');
        }

        if (!$this->getRecipients()->count()) {
            throw new \InvalidArgumentException('You must add a recipient');
        }

        if (!$this->getSubject()) {
            throw new \InvalidArgumentException('You must provide a subject');
        }

        if (!$this->getTemplateId()) {
            throw new \InvalidArgumentException('You must provide a template id');
        }

        if ($this->attachments->count()) {
            $size = 0;
            foreach ($this->attachments as $attachment) {
                $size += $attachment->Size;
            }

            if ($size = round(($size/(1024*1024))*10)/10 > 30) {
                throw new \RuntimeException("The total size of your attachments exceed SendGrid's imposed limit of 30 MB [Currently: $size MB]");
            }
        }
    }

    /**
     * Get the recipient list
     *
     * @return ArrayList
     */
    public function getRecipients()
    {
        return $this->to;
    }

    /**
     * Add a recipient
     *
     * @param string $to               The recipients email address
     *
     * @param null   $name             The recipients name
     * @param null   $personalizations Template substitutes. Eg ['-button_title-' => 'Log in now']
     *
     * @return $this
     */
    public function addRecipient($to, $name = null, $personalizations = null)
    {
        if ($personalizations && !ArrayLib::is_associative($personalizations)) {
            throw new \InvalidArgumentException('Personalizations should be an associative array');
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("That is not a valid 'recipient' email address [$to]");
        }

        if ($record = $this->to->find('Email', $to)) {
            $record->Personalizations = $personalizations;
            $record->Name = $name;

            return $this;
        }

        $data = [];
        foreach ($personalizations as $k => $v) {
            $data[] = [
                'Key'   => $k,
                'Value' => $v
            ];
        }

        $this->to->push(ArrayData::create([
            'Email'            => $to,
            'Name'             => $name,
            'Personalizations' => $data
        ]));

        return $this;
    }

    /**
     * @return string
     */
    public function getTemplateId()
    {
        return $this->templateId;
    }

    /**
     * @param string $templateId
     *
     * @return $this
     */
    public function setTemplateId($templateId)
    {
        $this->templateId = $templateId;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @param string $subject
     *
     * @return $this
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @return string
     */
    public function getFromName()
    {
        return $this->fromName;
    }

    /**
     * @param string $fromName
     *
     * @return $this
     */
    public function setFromName($fromName)
    {
        $this->fromName = $fromName;

        return $this;
    }

    /**
     * @return string
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * @param $from
     *
     * @return $this
     */
    public function setFrom($from)
    {
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("That is not a valid 'from' email address [$from]");
        }

        $this->from = $from;

        return $this;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param string|DBHTMLText $body
     *
     * @return $this
     */
    public function setBody($body)
    {
        if ($body instanceof DBHTMLText) {
            $this->body = $body->RAW();

            return $this;
        }

        $this->body = $body;

        return $this;
    }

    /**
     * @return string
     */
    public function getReplyTo()
    {
        return $this->replyTo;
    }

    /**
     * @param string $replyTo
     *
     * @return $this
     */
    public function setReplyTo($replyTo)
    {
        if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("That is not a valid 'reply-to' email address [$replyTo]");
        }

        $this->replyTo = $replyTo;

        return $this;
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        $key = $this->config()->get('api_key');

        if (!$key) {
            throw new \RuntimeException('api_key must be defined');
        }

        return $key;
    }

    /**
     * @return ArrayList
     */
    public function getErrorMap()
    {
        $map = [
            ['Code' => 200, 'Message' => 'OK', 'Reason' => 'Your message is valid, but it is not queued to be delivered.'],
            ['Code' => 202, 'Message' => 'ACCEPTED', 'Reason' => 'Your message is both valid, and queued to be delivered.'],
            ['Code' => 400, 'Message' => 'BAD REQUEST', 'Reason' => ''],
            ['Code' => 401, 'Message' => 'UNAUTHORIZED', 'Reason' => 'You do not have authorization to make the request.'],
            ['Code' => 403, 'Message' => 'FORBIDDEN', 'Reason' => ''],
            ['Code' => 404, 'Message' => 'NOT FOUND', 'Reason' => 'The resource you tried to locate could not be found or does not exist.'],
            ['Code' => 405, 'Message' => 'METHOD NOT ALLOWED', 'Reason' => ''],
            ['Code' => 413, 'Message' => 'PAYLOAD TOO LARGE', 'Reason' => 'The JSON payload you have included in your request is too large.'],
            ['Code' => 415, 'Message' => 'UNSUPPORTED MEDIA TYPE', 'Reason' => ''],
            ['Code' => 429, 'Message' => 'TOO MANY REQUESTS', 'Reason' => 'The number of requests you have made exceeds SendGrid’s rate limitations'],
            ['Code' => 500, 'Message' => 'SERVER UNAVAILABLE', 'Reason' => 'An error occurred on a SendGrid server.'],
            ['Code' => 503, 'Message' => 'SERVICE NOT AVAILABLE', 'Reason' => 'The SendGrid v3 Web API is not available.']
        ];

        return ArrayList::create($map);
    }

    /**
     * @param $code
     *
     * @return ArrayData
     */
    public function getErrorDefinition($code)
    {
        $record = $this->getErrorMap()->find('Code', $code);

        if (!$record) {
            throw new \InvalidArgumentException('That code is not a response code you would receive from SendGrid');
        }

        return $record;
    }

    /**
     * @return ArrayList
     */
    public function getAttachments()
    {
        return $this->attachments;
    }
}
