<?php

use App\Http\Controllers\V1\Admin\Item\ItemsController;
use App\Http\Requests\ItemsRequest;
use App\Models\Item;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs(
        $user,
        ['*']
    );
});

test('get items', function () {
    $response = getJson('api/v1/items?page=1');

    $response->assertOk();
});

test('create item', function () {
    $item = Item::factory()->raw([
        'taxes' => [
            Tax::factory()->raw(),
            Tax::factory()->raw(),
        ],
    ]);

    $response = postJson('api/v1/items', $item);

    $this->assertDatabaseHas('items', [
        'name' => $item['name'],
        'description' => $item['description'],
        'price' => $item['price'],
        'company_id' => $item['company_id'],
    ]);

    $this->assertDatabaseHas('taxes', [
        'item_id' => $response->getData()->data->id,
    ]);

    $response->assertOk();
});

test('store validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        ItemsController::class,
        'store',
        ItemsRequest::class
    );
});

test('get item', function () {
    $item = Item::factory()->create();

    $response = getJson("api/v1/items/{$item->id}");

    $response->assertOk();

    $this->assertDatabaseHas('items', [
        'name' => $item['name'],
        'description' => $item['description'],
        'price' => $item['price'],
        'company_id' => $item['company_id'],
    ]);
});

test('update item', function () {
    $item = Item::factory()->create();

    $update_item = Item::factory()->raw([
        'taxes' => [
            Tax::factory()->raw(),
        ],
    ]);

    $response = putJson('api/v1/items/'.$item->id, $update_item);

    $response->assertOk();

    $this->assertDatabaseHas('items', [
        'name' => $update_item['name'],
        'description' => $update_item['description'],
        'price' => $update_item['price'],
        'company_id' => $update_item['company_id'],
    ]);

    $this->assertDatabaseHas('taxes', [
        'item_id' => $item->id,
    ]);
});

test('update item preserves original company even when header selects another company', function () {
    $user = User::find(1);
    $initialCompany = $user->companies()->first();
    $secondCompany = \App\Models\Company::factory()->create();
    $secondCompany->setupDefaultData();
    $user->companies()->attach($secondCompany->id);
    \Silber\Bouncer\BouncerFacade::scope()->to($secondCompany->id);
    $user->assign('super admin');

    $item = Item::factory()->create([
        'company_id' => $initialCompany->id,
    ]);

    $this->withHeaders([
        'company' => $secondCompany->id,
    ]);

    $updateItem = [
        'name' => 'Updated Name',
        'price' => 150,
        'description' => 'Updated Desc',
        'company_id' => $secondCompany->id,
    ];

    $response = putJson('api/v1/items/'.$item->id, $updateItem);
    $response->assertOk();

    $this->assertDatabaseHas('items', [
        'id' => $item->id,
        'company_id' => $initialCompany->id,
        'name' => 'Updated Name',
    ]);
});

test('update validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        ItemsController::class,
        'update',
        ItemsRequest::class
    );
});

test('delete multiple items', function () {
    $items = Item::factory()->count(5)->create();

    $data = [
        'ids' => $items->pluck('id'),
    ];

    postJson("/api/v1/items/delete", $data)->assertOk();

    foreach ($items as $item) {
        $this->assertModelMissing($item);
    }
});

test('search items', function () {
    $filters = [
        'page' => 1,
        'limit' => 15,
        'search' => 'doe',
        'price' => 6,
        'unit' => 'kg',
    ];

    $queryString = http_build_query($filters, '', '&');

    $response = getJson('api/v1/items?'.$queryString);

    $response->assertOk();
});
