[![Build Status](https://travis-ci.org/vulcandigital/silverstripe-sendgrid.svg?branch=master)](https://travis-ci.org/vulcandigital/silverstripe-sendgrid)

## silverstripe-sendgrid
A module to assist developers in sending template emails via SendGrid

## Requirements
* silverstripe/framework: ^4.0

## Installation
```bash
composer require vulcandigital/silverstripe-sendgrid *
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
$sendGrid->setSubject("We have a sale for your!");
$sendGrid->setFrom('marketing@example.com');
$sendGrid->setFromName('My Site');
$sendGrid->setReplyTo('sales@example.com');
$sendGrid->addRecipient('reece@vulcandigital.co.nz', 'Reece Alexander', [
    ':salutation' => 'Mr',
    ':button_link' => 'https://example.com/verify/aASdGdjnklashewjk12321hjkasd213'
]);
$sendGrid->setBody("<p>We thought you'd like this awesome t-shirt!</p>");
$sendGrid->setTemplateId('your-template-id');
$sendGrid->send();
```

You can add as many recipients as you want.

## License
[BSD-3-Clause](LICENSE.md) - [Vulcan Digital Ltd](https://vulcandigital.co.nz)