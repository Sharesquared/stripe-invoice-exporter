#!/usr/bin/env php

<?php

#CSV_FILE_INVOICES=invoices.csv CSV_FILE_CREDITNOTES=creditnotes.csv ./download-from-csv.php

use Stripe\StripeClient;

require __DIR__ . '/vendor/autoload.php';

if (!isset($_SERVER['STRIPE_KEY'])) {
    echo 'Set a Stripe API Key in the "STRIPE_KEY" environment variable' . PHP_EOL;
    exit(1);
}

$stripe = new StripeClient($_SERVER['STRIPE_KEY']);
$chargesOnly = ($_SERVER['CHARGES_ONLY'] ?? true);
$csvFileInvoices = ($_SERVER['CSV_FILE_INVOICES'] ?? 'invoices.csv'); //scheme: id
$csvFileCreditNotes = ($_SERVER['CSV_FILE_CREDITNOTES'] ?? 'creditnotes.csv'); //scheme: id,invoice_id

if (($handle = fopen($csvFileInvoices, 'r')) !== FALSE) {
    while (($data = fgets($handle)) !== FALSE) {

        $invoiceId = trim($data);

        if (!$invoiceId || $invoiceId == 'id') {
            continue;
        }

        $invoice = $stripe->invoices->retrieve($invoiceId);

        if (!$invoice) {
            continue;
        }

        if (!$chargesOnly) {

            $path = sprintf('invoices/%s_%s.pdf', date(DATE_ATOM, $invoice->created), $invoice->id);
            if (file_exists($path)) {
                continue;
            }

            echo sprintf("Downloading invoice %s...\n" . PHP_EOL, $invoice->invoice_pdf);
            $fp = fopen($path, 'w');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $invoice->invoice_pdf);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_FILE, $fp);

            curl_exec($ch);
            fclose($fp);
        }

        $path = sprintf('receipts/%s_%s.pdf',  str_replace([":", "+"], ["-", ""], date(DATE_ATOM, $invoice->created)), $invoice->id);
        if (file_exists($path)) {
            continue;
        }

        $chargeId = $invoice->charge;

        if (!$chargeId) {
            echo("Skipping invoice $invoice->id because it has no charge associated with it (e.g. paid out of band)\n");
            echo("Please download the invoice (receipt) manually from here $invoice->hosted_invoice_url\n and save it into invoices/ (or receipts/)\n");
            echo "Press Enter once to continue...\n";
            fgets(STDIN);
            echo "Press Enter again to proceed...\n";
            fgets(STDIN);
            echo "You pressed Enter twice. Script continues...\n";
            continue;
        }

        $charge = $stripe->charges->retrieve($chargeId);
        $chargePdf = $charge->receipt_url;

        $url = str_replace("?s=ap", "", $chargePdf) . "/pdf";
        echo sprintf("Downloading receipt %s...\n\n" . PHP_EOL, $url);
        $fp = fopen($path, 'w');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_FILE, $fp);

        curl_exec($ch);
        fclose($fp);
    }

    fclose($handle);

}


if (($handle = fopen($csvFileCreditNotes, 'r')) !== FALSE) {
    while (($data = fgets($handle)) !== FALSE) {
        $csvData = str_getcsv($data);
        $creditNoteId = trim($csvData[0] ?? null);

        if (!$creditNoteId || $creditNoteId == 'id') {
            continue;
        }

        $creditNote = $stripe->creditNotes->retrieve($creditNoteId);

        if (!$creditNote) {
            continue;
        }

        $path = sprintf('creditnotes/%s_%s.pdf',  str_replace([":", "+"], ["-", ""], date(DATE_ATOM, $creditNote->created)), $creditNote->id);
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

    fclose($handle);
}

echo "Done!" . PHP_EOL;