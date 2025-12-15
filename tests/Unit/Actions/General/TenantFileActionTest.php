<?php

use App\Actions\General\EasyHashAction;
use App\Actions\General\TenantFileAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

test('it can save a public file', function () {
    Storage::fake('public');
    $tenantId = 1;
    $file = UploadedFile::fake()->image('test.jpg');

    $result = TenantFileAction::save($tenantId, $file, true, 'images');

    expect($result->url)->toContain('file/');
    expect($result->local_path)->toContain('tenants/1/images/');
    expect($result->type)->toBe('image');

    // Storage::disk('public')->assertExists('tenants/1/images/' . $result->local_path); // Incorrect double path
    
    Storage::disk('public')->assertExists($result->local_path);
});

test('it can save a private file', function () {
    Storage::fake('local');
    $tenantId = 1;
    $file = UploadedFile::fake()->create('document.pdf', 100);

    $result = TenantFileAction::save($tenantId, $file, false, 'documents');

    expect($result->url)->toContain('file/');
    expect($result->local_path)->toContain('tenants/1/documents/');
    expect($result->type)->toBe('document');

    Storage::disk('local')->assertExists($result->local_path);
});

test('it can get a file', function () {
    Storage::fake('public');
    $tenantId = 1;
    $file = UploadedFile::fake()->image('test.jpg');
    $path = 'tenants/1/images/test.jpg';
    Storage::disk('public')->put($path, $file->getContent());

    $content = TenantFileAction::get($tenantId, 'images/test.jpg', true);

    expect($content)->not->toBeNull();
});

test('it can delete a file', function () {
    Storage::fake('public');
    $tenantId = 1;
    $file = UploadedFile::fake()->image('test.jpg');
    $path = 'tenants/1/images/test.jpg';
    Storage::disk('public')->put($path, $file->getContent());

    $deleted = TenantFileAction::delete($tenantId, 'images/test.jpg', null, true);

    expect($deleted)->toBeTrue();
    Storage::disk('public')->assertMissing($path);
});
