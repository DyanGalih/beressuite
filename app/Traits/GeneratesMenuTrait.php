<?php

namespace App\Traits;

trait GeneratesMenuTrait
{
    public function generateMenu($key, $user)
    {
        $menu = [];
        $items = config("crater.{$key}", []);

        if (! is_array($items)) {
            return $menu;
        }

        foreach ($items as $data) {
            if ($user->checkAccess($data)) {
                $menu[] = [
                    'title' => $data['title'],
                    'link' => $data['link'],
                    'icon' => $data['icon'],
                    'name' => $data['name'],
                    'group' => $data['group'],
                ];
            }
        }

        return $menu;
    }
}
