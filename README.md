# Stripe Invoice Exporter

Download all your Stripe PDF invoices in bulk.

# Prerequisites

You need a working installation of [PHP](https://php.net) and of [Composer](https://getcomposer.org/).

## Install

1. Run `composer install` to install the Stripe SDK.
2. Create a new restricted key with the `Read` right for `Invoices` and `Credit notes` resource type:
    ![Screenshot](docs/restricted-key.png)
3. Copy the generated key.

## Usage

    export STRIPE_KEY=... #
    START_DATE=2024-01-01 END_DATE=2024-01-31 WITH_CREDIT_NOTES=false ./download.php

The invoices will be downloaded in the `invoices/` directory.
The credit notes will be downloaded in the `creditNotes/` directory.

## Why

This is useful in the automation of the monthly finance obligations.

### Credit notes

Omit the argument or set it to `true`. Only notes created in the specified interval will be fetched.
Keep in mind that credit notes are equivalent to invoices paid by us, and therefore they need to be accounted for (for example, in terms of VAT claims etc).