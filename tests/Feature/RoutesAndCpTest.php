<?php

use Goldnead\BrandContext\Models\Brand;
use Goldnead\Certificates\Facades\Certificates;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Statamic\Facades\Role;
use Statamic\Facades\User;

it('registers no front-end routes when the switch is off', function () {
    config(['certificates.routes.enabled' => false]);
    Route::setRoutes(new RouteCollection);

    require __DIR__.'/../../routes/web.php';
    Route::getRoutes()->refreshNameLookups();

    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));

    expect(Route::has('certificates.verify'))->toBeFalse()
        ->and(Route::has('certificates.download'))->toBeFalse()
        ->and(Certificates::verifyUrl($certificate))->toBeNull()
        ->and(Certificates::downloadUrl($certificate))->toBeNull();

    $this->get('/certificates/verify/'.$certificate->code)->assertNotFound();
});

it('closes routes a cache kept alive once the switch is off', function () {
    $ada = $this->makeUser('ada@example.com', 'Ada');
    $certificate = Certificates::issue($ada, $this->makeCourse('Kurs'));
    config(['certificates.routes.enabled' => false]);

    $this->get('/certificates/verify/'.$certificate->code)->assertNotFound();
    $this->actingAs($ada)->get('/certificates/'.$certificate->code.'/download')->assertNotFound();
});

function cpUser(array $permissions): Statamic\Contracts\Auth\User
{
    $role = Role::make('certificates-test-'.md5(implode(',', $permissions)))->permissions(['access cp', ...$permissions]);
    $role->save();

    $user = User::make()->email(uniqid().'@example.com')->assignRole($role);
    $user->save();

    return $user;
}

it('shows the certificates to a user with manage certificates', function () {
    Certificates::issue($this->makeUser('ada@example.com', 'Ada Lovelace'), $this->makeCourse('Stimmbildung im Chor'));

    $this->actingAs(cpUser(['manage certificates']))
        ->get('/cp/certificates')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('certificates::Certificates/Index')
            ->has('rows', 1)
            ->where('rows.0.learner_name', 'Ada Lovelace')
            ->where('rows.0.course_title', 'Stimmbildung im Chor')
            ->where('rows.0.status', 'valid')
            ->has('initialColumns', 6));
});

it('shows the revoke reason in its own column', function () {
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));
    Certificates::revoke($certificate, 'Doppelt ausgestellt');

    $this->actingAs(cpUser(['manage certificates']))
        ->get('/cp/certificates')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.0.status', 'revoked')
            ->where('rows.0.revoked_reason', 'Doppelt ausgestellt')
            ->where('initialColumns.5.field', 'revoked_reason'));
});

it('lists only the current brand\'s certificates', function () {
    config(['brand-context.multi_brand' => true]);
    $other = Brand::create(['handle' => 'other', 'name' => 'Other'])->id;
    app('brand-context')->runFor($other, fn () => Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs')));
    app('brand-context')->setCurrent(app('brand-context')->defaultId());

    $this->actingAs(cpUser(['manage certificates']))
        ->get('/cp/certificates')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0));

    config(['brand-context.multi_brand' => false]);
});

it('refuses the page and the revoke route without the permission', function () {
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));
    $user = cpUser([]);

    // Core turns a failed `can:` in the CP into a redirect with an error
    // toast rather than a bare 403. Either way nothing renders or changes.
    $page = $this->actingAs($user)->get('/cp/certificates');
    $revoke = $this->actingAs($user)->post('/cp/certificates/'.$certificate->id.'/revoke', ['reason' => 'x']);

    expect($page->status())->toBeIn([302, 403])
        ->and($page->headers->get('X-Inertia'))->toBeNull()
        ->and($revoke->status())->toBeIn([302, 403]);

    expect($certificate->fresh()->isRevoked())->toBeFalse();
});

it('revokes from the Control Panel, and only with a reason', function () {
    $certificate = Certificates::issue($this->makeUser('ada@example.com', 'Ada'), $this->makeCourse('Kurs'));
    $user = cpUser(['manage certificates']);

    $this->actingAs($user)->post('/cp/certificates/'.$certificate->id.'/revoke', ['reason' => ''])
        ->assertSessionHasErrors('reason');
    expect($certificate->fresh()->isRevoked())->toBeFalse();

    app()->setLocale('de');
    $this->actingAs($user)->post('/cp/certificates/'.$certificate->id.'/revoke', ['reason' => 'Doppelt ausgestellt'])
        ->assertRedirect('/cp/certificates')
        ->assertSessionHas('success', 'Zertifikat '.implode('-', str_split($certificate->code, 4)).' wurde widerrufen.');

    expect($certificate->fresh()->revoked_reason)->toBe('Doppelt ausgestellt');
});
