<?php

use Crater\Models\User;
use Crater\Traits\GeneratesMenuTrait;

test('GeneratesMenuTrait builds main_menu and setting_menu directly from crater config', function () {
    $generator = new class () {
        use GeneratesMenuTrait;
    };

    $owner = new class () extends User {
        public function isOwner(): bool
        {
            return true;
        }
    };

    $mainMenu = $generator->generateMenu('main_menu', $owner);
    $settingMenu = $generator->generateMenu('setting_menu', $owner);

    expect($mainMenu)->toHaveCount(count(config('crater.main_menu')))
        ->and($mainMenu[0])->toHaveKeys(['title', 'link', 'icon', 'name', 'group'])
        ->and($settingMenu)->toHaveCount(count(config('crater.setting_menu')))
        ->and($settingMenu[0])->toHaveKeys(['title', 'link', 'icon', 'name', 'group']);
});

test('User::checkAccess enforces owner_only and ability checks on menu config arrays', function () {
    $restrictedUser = new class () extends User {
        public function isOwner(): bool
        {
            return false;
        }

        public function can($abilities, $arguments = []): bool
        {
            return $abilities === 'view-invoice' && $arguments === \Crater\Models\Invoice::class;
        }
    };

    expect($restrictedUser->checkAccess([
        'owner_only' => false,
        'ability' => '',
        'model' => '',
    ]))->toBeTrue();

    expect($restrictedUser->checkAccess([
        'owner_only' => true,
        'ability' => '',
        'model' => '',
    ]))->toBeFalse();

    expect($restrictedUser->checkAccess([
        'owner_only' => false,
        'ability' => 'view-invoice',
        'model' => \Crater\Models\Invoice::class,
    ]))->toBeTrue();

    expect($restrictedUser->checkAccess([
        'owner_only' => false,
        'ability' => 'delete-invoice',
        'model' => \Crater\Models\Invoice::class,
    ]))->toBeFalse();
});
