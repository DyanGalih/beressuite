<?php

namespace Crater\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FileDiskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $credentials = is_string($this->credentials) ? json_decode($this->credentials, true) : $this->credentials;
        if (is_array($credentials)) {
            foreach (['secret', 'password', 'token', 'key'] as $secretKey) {
                if (isset($credentials[$secretKey]) && ! empty($credentials[$secretKey])) {
                    $credentials[$secretKey] = '********';
                }
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'driver' => $this->driver,
            'set_as_default' => $this->set_as_default,
            'credentials' => $credentials,
            'company_id' => $this->company_id,
        ];
    }
}
