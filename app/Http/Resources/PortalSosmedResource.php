<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PortalSosmedResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            'nama_platform' => $this->nama_platform,
            'url_link'      => $this->url_link,
            'tipe'          => $this->tipe,
            
            // Memberikan bantuan class icon untuk Frontend (Opsional tapi sangat membantu)
            'icon_class'    => $this->getIconClass($this->nama_platform),

            // Merapikan format tanggal agar tidak ada format ISO "T00:00:00Z"
            'created_at'    => $this->created_at ? $this->created_at->format('d-m-Y H:i') : null,
            'updated_at'    => $this->updated_at ? $this->updated_at->format('d-m-Y H:i') : null,
        ];
    }

    /**
     * Fungsi pembantu untuk menentukan class icon berdasarkan nama platform
     */
    private function getIconClass($platform)
    {
        $platform = strtolower($platform);
        return match (true) {
            str_contains($platform, 'instagram') => 'fab fa-instagram',
            str_contains($platform, 'facebook')  => 'fab fa-facebook',
            str_contains($platform, 'youtube')   => 'fab fa-youtube',
            str_contains($platform, 'twitter')   => 'fab fa-twitter',
            str_contains($platform, 'tiktok')    => 'fab fa-tiktok',
            default                              => 'fas fa-link',
        };
    }
}