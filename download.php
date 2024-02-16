#!/usr/bin/env php

<?php

# START_DATE=2024-01-01 END_DATE=2024-01-31 ./download.php

use Carbon\Carbon;
use Stripe\Invoice;
use Stripe\CreditNote;
use Stripe\StripeClient;

require __DIR__ . '/vendor/autoload.php';

if (!isset($_SERVER['STRIPE_KEY'])) {
    echo 'Set a Stripe API Key in the "STRIPE_KEY" environment variable' . PHP_EOL;
    exit(1);
}

$stripe = new StripeClient($_SERVER['STRIPE_KEY']);
$startDate = Carbon::parse($_SERVER['START_DATE'])->timestamp;
$endDate = Carbon::parse($_SERVER['END_DATE'])->timestamp;
$withCreditNotes = ($_SERVER['WITH_CREDIT_NOTES'] == 'true' || !$_SERVER['WITH_CREDIT_NOTES']) ? true : false;

$invoices = $stripe->invoices->search(['query' => "created>=$startDate AND created<=$endDate"]);
foreach ($invoices->autoPagingIterator() as $invoice) {
    /** @var Invoice $invoice */
    if (!$invoice->invoice_pdf) {
        continue;
    }

    $path = sprintf('invoices/%s_%s.pdf', date(DATE_ATOM, $invoice->created), $invoice->id);
    if (file_exists($path)) {
        continue;
    }

    echo sprintf("Downloading invoice %s..." . PHP_EOL, $invoice->invoice_pdf);
    $fp = fopen($path, 'w');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $invoice->invoice_pdf);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_FILE, $fp);

    curl_exec($ch);
    fclose($fp);

}

// unfortunately they don't have search() for credit notes
if ($withCreditNotes) {
    $creditNotes = $stripe->creditNotes->all();
    foreach ($creditNotes->autoPagingIterator() as $creditNote) {
        /** @var CreditNote $creditNote */
        if (!$creditNote->pdf || !($creditNote->created >= $startDate && $creditNote->created <= $endDate)) {
            continue;
        }
    
        $path = sprintf('creditNotes/%s_%s.pdf', date(DATE_ATOM, $creditNote->created), $creditNote->id);
        if (file_exists($path)) {
            continue;
        }
    
        echo sprintf("Downloading credit note %s..." . PHP_EOL, $creditNote->pdf);
        $fp = fopen($path, 'w');
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $creditNote->pdf);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_FILE, $fp);
    
        curl_exec($ch);
        fclose($fp);
    }
}

echo "Done!" . PHP_EOL;
