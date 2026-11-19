<?php

namespace Crater\Traits;

use Carbon\Carbon;
use Crater\Models\Address;
use Crater\Models\CompanySetting;
use Crater\Models\FileDisk;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

trait GeneratesPdfTrait
{
    public function getGeneratedPDFOrStream(string $collection_name): Response
    {
        $pdf = $this->getGeneratedPDF($collection_name);
        if ($pdf && file_exists($pdf['path'])) {
            return response()->make(file_get_contents($pdf['path']), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$pdf['file_name'].'"',
            ]);
        }

        $locale = CompanySetting::getSetting('language',  $this->company_id);

        App::setLocale($locale);

        $pdf = $this->getPDFData();

        return response()->make($pdf->stream(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this[$collection_name.'_number'].'.pdf"',
        ]);
    }

    public function getGeneratedPDF(string $collection_name): Collection|false
    {
        try {
            $media = $this->getMedia($collection_name)->first();

            if ($media) {
                $file_disk = FileDisk::whereCompany($this->company_id)->find($media->custom_properties['file_disk_id'] ?? null);

                if (! $file_disk) {
                    return false;
                }

                $path = $file_disk->withStorage(fn () => $file_disk->driver === 'local'
                    ? $media->getPath()
                    : $media->getTemporaryUrl(Carbon::now()->addMinutes(5)));

                return collect([
                    'path' => $path,
                    'file_name' => $media->file_name,
                ]);
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    public function generatePDF(string $collection_name, string $file_name, bool $deleteExistingFile = false): bool|int|string
    {
        $save_pdf_to_disk = CompanySetting::getSetting('save_pdf_to_disk',  $this->company_id);

        if ($save_pdf_to_disk == 'NO') {
            return 0;
        }

        $file_disk = FileDisk::whereCompany($this->company_id)->whereSetAsDefault(true)->first();
        if (! $file_disk) {
            return false;
        }

        $locale = CompanySetting::getSetting('language',  $this->company_id);

        App::setLocale($locale);

        $pdf = $this->getPDFData();

        Storage::disk('local')->put('temp/'.$collection_name.'/'.$this->id.'/temp.pdf', $pdf->output());

        if ($deleteExistingFile) {
            $this->clearMediaCollection($this->id);
        }

        $media = Storage::disk('local')->path('temp/'.$collection_name.'/'.$this->id.'/temp.pdf');

        try {
            $file_disk->withStorage(fn ($disk) => $this->addMedia($media)
                ->withCustomProperties(['file_disk_id' => $file_disk->id])
                ->usingFileName($file_name.'.pdf')
                ->toMediaCollection($collection_name, $disk));

            Storage::disk('local')->deleteDirectory('temp/'.$collection_name.'/'.$this->id);

            return true;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function getFieldsArray(): array
    {
        $customer = $this->customer;
        $shippingAddress = $customer->shippingAddress ?? new Address();
        $billingAddress = $customer->billingAddress ?? new Address();
        $companyAddress = $this->company->address ?? new Address();

        $fields = [
            '{SHIPPING_ADDRESS_NAME}' => $shippingAddress->name,
            '{SHIPPING_COUNTRY}' => $shippingAddress->country_name,
            '{SHIPPING_STATE}' => $shippingAddress->state,
            '{SHIPPING_CITY}' => $shippingAddress->city,
            '{SHIPPING_ADDRESS_STREET_1}' => $shippingAddress->address_street_1,
            '{SHIPPING_ADDRESS_STREET_2}' => $shippingAddress->address_street_2,
            '{SHIPPING_PHONE}' => $shippingAddress->phone,
            '{SHIPPING_ZIP_CODE}' => $shippingAddress->zip,
            '{BILLING_ADDRESS_NAME}' => $billingAddress->name,
            '{BILLING_COUNTRY}' => $billingAddress->country_name,
            '{BILLING_STATE}' => $billingAddress->state,
            '{BILLING_CITY}' => $billingAddress->city,
            '{BILLING_ADDRESS_STREET_1}' => $billingAddress->address_street_1,
            '{BILLING_ADDRESS_STREET_2}' => $billingAddress->address_street_2,
            '{BILLING_PHONE}' => $billingAddress->phone,
            '{BILLING_ZIP_CODE}' => $billingAddress->zip,
            '{COMPANY_NAME}' => $this->company->name,
            '{COMPANY_COUNTRY}' => $companyAddress->country_name,
            '{COMPANY_STATE}' => $companyAddress->state,
            '{COMPANY_CITY}' => $companyAddress->city,
            '{COMPANY_ADDRESS_STREET_1}' => $companyAddress->address_street_1,
            '{COMPANY_ADDRESS_STREET_2}' => $companyAddress->address_street_2,
            '{COMPANY_PHONE}' => $companyAddress->phone,
            '{COMPANY_ZIP_CODE}' => $companyAddress->zip,
            '{CONTACT_DISPLAY_NAME}' => $customer->name,
            '{PRIMARY_CONTACT_NAME}' => $customer->contact_name,
            '{CONTACT_EMAIL}' => $customer->email,
            '{CONTACT_PHONE}' => $customer->phone,
            '{CONTACT_WEBSITE}' => $customer->website,
        ];

        $customFields = $this->fields;
        $customerCustomFields = $this->customer->fields;

        foreach ($customFields as $customField) {
            $fields['{'.$customField->customField->slug.'}'] = $customField->defaultAnswer;
        }

        foreach ($customerCustomFields as $customField) {
            $fields['{'.$customField->customField->slug.'}'] = $customField->defaultAnswer;
        }

        foreach ($fields as $key => $field) {
            $fields[$key] = htmlspecialchars($field ?? '', ENT_QUOTES, 'UTF-8');
        }

        return $fields;
    }

    public function getFormattedString(?string $format): string
    {
        if ($format === null || $format === '') {
            return '';
        }

        $values = array_merge($this->getFieldsArray(), $this->getExtraFields());

        $str = nl2br(strtr($format, $values));

        $str = preg_replace('/{(.*?)}/', '', $str);

        $str = preg_replace("/<[^\/>]*>([\s]?)*<\/[^>]*>/", '', $str);

        $str = str_replace('<p>', '', $str);

        $str = str_replace('</p>', '</br>', $str);

        return $str;
    }
}
