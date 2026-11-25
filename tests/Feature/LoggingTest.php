<?php

use Goldnead\Certificates\Certificates as CertificatesService;
use Goldnead\Certificates\Facades\Certificates;
use Goldnead\Courses\Events\CourseCompleted;
use Illuminate\Support\Facades\Log;

it('logs the learner and the course when the completion listener hits an unexpected error', function () {
    $user = $this->makeUser('ada@example.com', 'Ada');
    $course = $this->makeCourse('Kurs');

    $broken = Mockery::mock(CertificatesService::class);
    $broken->shouldReceive('issue')->andThrow(new RuntimeException('disk gone'));
    $this->app->instance(CertificatesService::class, $broken);
    Log::spy();

    CourseCompleted::dispatch((string) $user->id(), $course, 'kurs');

    Log::shouldHaveReceived('error')
        ->withArgs(fn ($message, $context) => ($context['user'] ?? null) === (string) $user->id()
            && ($context['course'] ?? null) === $course)
        ->once();
});

it('logs the subject and the course when no default brand is found and the certificate goes out without one', function () {
    $user = $this->makeUser('ada@example.com', 'Ada');
    $course = $this->makeCourse('Kurs');

    $brand = Mockery::mock();
    $brand->shouldReceive('current')->andThrow(new RuntimeException('no default brand'));
    $brand->shouldReceive('currentId')->andReturn('00000000-0000-0000-0000-000000000001');
    $this->app->instance('brand-context', $brand);
    Log::spy();

    $certificate = Certificates::issue($user, $course);

    expect($certificate->brand_handle)->toBeNull();
    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message, $context) => ($context['subject_id'] ?? null) === (string) $user->id()
            && ($context['course'] ?? null) === $course)
        ->once();
});
