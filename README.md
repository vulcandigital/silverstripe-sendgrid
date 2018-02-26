[![Build Status](https://travis-ci.org/vulcandigital/silverstripe-sendgrid.svg?branch=master)](https://travis-ci.org/vulcandigital/silverstripe-sendgrid) [![codecov](https://codecov.io/gh/vulcandigital/silverstripe-sendgrid/branch/master/graph/badge.svg)](https://codecov.io/gh/vulcandigital/silverstripe-sendgrid) [![Latest Stable Version](https://poser.pugx.org/vulcandigital/silverstripe-sendgrid/v/stable)](https://packagist.org/packages/vulcandigital/silverstripe-sendgrid) [![Total Downloads](https://poser.pugx.org/vulcandigital/silverstripe-sendgrid/downloads)](https://packagist.org/packages/vulcandigital/silverstripe-sendgrid) [![License](https://poser.pugx.org/vulcandigital/silverstripe-sendgrid/license)](https://packagist.org/packages/vulcandigital/silverstripe-sendgrid)

## silverstripe-sendgrid
A module to assist developers in sending template emails via SendGrid

## Requirements
* silverstripe/framework: ^4.0

## Installation
```bash
composer require vulcandigital/silverstripe-sendgrid
```

## Configuration
**mysite/_config/sendgrid.yml:**
```yml
Vulcan\SendGrid\SendGrid:
  api_key: 'REPLACE-WITH-YOUR-API-KEY'
```

## Usage
```php
$sendGrid = \Vulcan\SendGrid\SendGrid::create();
$sendGrid->setSubject("We have a sale for you!");
$sendGrid->setFrom('marketing@example.com');
$sendGrid->setFromName('My Site');
$sendGrid->setReplyTo('sales@example.com');
$sendGrid->addRecipient('reece@vulcandigital.co.nz', 'Reece Alexander', [
    ':salutation' => 'Mr',
    ':button_link' => 'https://example.com/store/offer?id=aASdGdjnklashewjk12321hjkasd213'
]);
$sendGrid->setBody("<p>We thought you'd like this awesome t-shirt!</p>");
$sendGrid->setTemplateId('your-template-id');
$sendGrid->addAttachment(Image::get()->first());
$sendGrid->send();
```

You can add as many recipients as you want.

## Substitutions & Custom Arguments
Substitutions and custom arguments are practically the same thing, the only difference is that custom arguments are applied globally regardless of the recipient where substitutions are variable replacements that can differ per recipient.

> Substitutions will always override any custom argument

### Substitutions
Substitutions are variables that can be replaced per recipient

```php
$sendGrid->addRecipient('john@doe.com', 'John Doe', [
    ':salutation' => 'Mr',
    ':first_name' => 'John',
    ':last_name' => 'Doe'
]);
$sendGrid->addRecipient('jane@doe.com', 'Jane Doe', [
    ':salutation' => 'Mrs',
    ':first_name' => 'Jane',
    ':last_name' => 'Doe'
]);
```

### Custom Arguments
Custom arguments are applied globally across all recipients unless a substitution has overridden it

```php
$sendGrid->addCustomArg(':year', DBDatetime::now()->Year());
```

### Attachments
You can add as many attachments as you want totalling up to 30 MB. The attachment must be a `File` object or a subclass of it such as itself or `Image`.

```php
$file = Image::get()->first();
$sendGrid->addAttachment($file, $filename = null, $forcePublish = false); 
```

or you can use an absolute path to a file instead:

```php
$sendgrid->addAttachment('/public_html/path/to/image.png'));
$sendgrid->addAttachment(Controller::join_links(Director::baseFolder(), '/path/to/image2.png'));
```

If you provide `$filename`, make sure you provide the correct extension as well to prevent any errors

If the provided file is a `File` object and `$forcePublish` is set to `true` _and_ the `File` you have provided has not been published, it will be forcibly published.

### Scheduling
You can schedule emails to be sent at a later date:

```php
$sendGrid->setScheduleTo(DBDatetime::now()->getTimestamp() + 3600); // Schedule to send in 1 hour
```

> **Important:** Ensure that you have specified your correct timezone in your SendGrid account's settings, otherwise this may have unexpected results.
> 
> Your database timezone should also match the timezone you have specified in your account. See [Core Environment Variables](https://docs.silverstripe.org/en/4/getting_started/environment_management/#core-environment-variables) for information on how to modify the timezone used by your database.
>
> It is always advised when dealing with dates and times in SilverStripe to use the functionality it has provided you as shown in the example above.

### Sandbox Mode
```php
$sendGrid->setSandboxMode(true);
```
If everything is OK, $sendGrid->send() will return true otherwise an error will be thrown.

## License
[BSD-3-Clause](LICENSE.md) - [Vulcan Digital Ltd](https://vulcandigital.co.nz)
