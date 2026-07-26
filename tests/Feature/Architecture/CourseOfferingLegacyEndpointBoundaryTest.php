<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('keeps migrated course-offering endpoints on their owner controllers', function (): void {
    $contracts = [
        ['course-offerings.create-survey', 'course-offerings/{courseOffering}/survey', ['POST'], 'App\\Modules\\Engagement\\Http\\Web\\CourseOfferingSurveyController', ['web', 'auth', 'verified', 'can:edit_course_offering']],
        ['course-offerings.split.show', 'course-offerings/{courseOffering}/split', ['GET', 'HEAD'], 'App\\Modules\\Academic\\Delivery\\Http\\Web\\CourseOfferingSplitController@show', ['web', 'auth', 'verified', 'can:edit_course_offering']],
        ['course-offerings.split.perform', 'course-offerings/{courseOffering}/split', ['POST'], 'App\\Modules\\Academic\\Delivery\\Http\\Web\\CourseOfferingSplitController@perform', ['web', 'auth', 'verified', 'can:edit_course_offering']],
        ['api.course-offerings.statistics', 'api/course-offerings/statistics', ['GET', 'HEAD'], 'App\\Modules\\Academic\\Delivery\\Http\\Web\\CourseOfferingStatisticsController', ['web', 'auth', 'verified', 'can:view_course_offering']],
    ];

    foreach ($contracts as [$name, $uri, $methods, $action, $middleware]) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)
            ->not->toBeNull()
            ->and($route?->uri())->toBe($uri)
            ->and($route?->methods())->toContain(...$methods)
            ->and($route?->getActionName())->toBe($action)
            ->and($route?->gatherMiddleware())->toContain(...$middleware);
    }
});
