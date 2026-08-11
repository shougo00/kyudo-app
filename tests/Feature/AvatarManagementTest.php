<?php

use App\Models\Avatar;
use App\Models\Item;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

function makeAvatarItem(string $type, array $overrides = []): Item
{
    $layout = Item::defaultLayoutFor($type);

    return Item::create(array_merge([
        'name' => $type . ' item',
        'type' => $type,
        'price' => 0,
        'image_path' => $type . '/sample.png',
        'position_x' => $layout['position_x'],
        'position_y' => $layout['position_y'],
        'display_width' => $layout['display_width'],
        'display_height' => $layout['display_height'],
        'z_index' => $layout['z_index'],
        'is_active' => true,
    ], $overrides));
}

it('lets KANRI import and adjust avatar items', function () {
    $admin = User::where('username', 'KANRI')->firstOrFail();
    makeAvatarItem('item', ['name' => 'Existing item']);

    $uploadedPath = null;

    try {
        $this->actingAs($admin)
            ->get(route('admin.system.avatar-items'))
            ->assertOk()
            ->assertSee('アバター素材管理')
            ->assertSee('幅（大きさ）')
            ->assertSee('高さ（大きさ）');

        $this->actingAs($admin)
            ->post(route('admin.system.avatar-items.store'), [
                'type' => 'face',
                'name' => 'Imported face',
                'image' => UploadedFile::fake()->create('face.png', 10, 'image/png'),
            ])
            ->assertRedirect();

        $item = Item::where('name', 'Imported face')->firstOrFail();
        $uploadedPath = public_path('avatars/' . $item->image_path);

        expect($item->type)->toBe('face');
        expect(File::exists($uploadedPath))->toBeTrue();

        $this->actingAs($admin)
            ->patch(route('admin.system.avatar-items.update', $item), [
                'type' => 'face',
                'name' => 'Adjusted face',
                'position_x' => 81,
                'position_y' => 33,
                'display_width' => 144,
                'display_height' => 155,
                'z_index' => 77,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $item->refresh();

        expect($item->name)->toBe('Adjusted face');
        expect($item->position_x)->toBe(81);
        expect($item->position_y)->toBe(33);
        expect($item->display_width)->toBe(144);
        expect($item->display_height)->toBe(155);
        expect($item->z_index)->toBe(77);
    } finally {
        if ($uploadedPath) {
            File::delete($uploadedPath);
        }
    }
});

it('blocks non KANRI users from avatar item management', function () {
    $user = User::factory()->create([
        'username' => 'normal-user',
        'is_admin' => false,
    ]);

    $this->actingAs($user)
        ->get(route('admin.system.avatar-items'))
        ->assertForbidden();
});

it('saves the new five part avatar selection', function () {
    $user = User::factory()->create();

    $face = makeAvatarItem('face', ['name' => 'face']);
    $body = makeAvatarItem('body', ['name' => 'body']);
    $pants = makeAvatarItem('pants', ['name' => 'pants']);
    $shoes = makeAvatarItem('shoes', ['name' => 'shoes']);
    $item = makeAvatarItem('item', ['name' => 'item']);

    $this->actingAs($user)
        ->post(route('avatar.update'), [
            'face_id' => $face->id,
            'body_id' => $body->id,
            'pants_id' => $pants->id,
            'shoes_id' => $shoes->id,
            'item_id' => $item->id,
        ])
        ->assertRedirect(route('avatar.show'));

    $avatar = Avatar::where('user_id', $user->id)->firstOrFail();

    expect($avatar->face_id)->toBe($face->id);
    expect($avatar->body_id)->toBe($body->id);
    expect($avatar->pants_id)->toBe($pants->id);
    expect($avatar->shoes_id)->toBe($shoes->id);
    expect($avatar->item_id)->toBe($item->id);
});

it('renders selected avatar layers without the old fixed body base', function () {
    $user = User::factory()->create();
    $face = makeAvatarItem('face', [
        'name' => 'Visible face',
        'image_path' => 'face/visible-face.svg',
    ]);
    $body = makeAvatarItem('body', [
        'name' => 'Body with hands',
        'image_path' => 'top/body-with-hands.svg',
    ]);

    Avatar::create([
        'user_id' => $user->id,
        'face_id' => $face->id,
        'body_id' => $body->id,
    ]);

    $this->actingAs($user)
        ->get(route('avatar.show'))
        ->assertOk()
        ->assertDontSee('avatars/body/body1.svg')
        ->assertSee('top/body-with-hands.svg')
        ->assertSee('face/visible-face.svg');
});
