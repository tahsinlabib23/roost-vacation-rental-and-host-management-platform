<?php

namespace Tests\Feature\Messaging;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingSearchTest extends TestCase
{
    use BuildsMessagingFixtures, RefreshDatabase;

    public function test_both_names_are_required_and_must_match_the_same_published_pair(): void
    {
        $guest = $this->user('Search Acceptance Guest', 'guest');
        $maya = $this->user('Maya Duplicate', 'host');
        $otherMaya = $this->user('Maya Duplicate', 'host');
        $expected = $this->property($maya, 'Garden House');
        $this->property($maya, 'City Loft');
        $this->property($otherMaya, 'Garden House Draft', 'draft');

        $this->actingAs($guest)->getJson(route('messages.search', [
            'host_name' => '',
            'property_name' => 'Garden',
        ]))->assertUnprocessable()->assertJsonValidationErrors('host_name');

        $this->actingAs($guest)->getJson(route('messages.search', [
            'host_name' => 'Maya Duplicate',
            'property_name' => 'Garden House',
        ]))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.property.id', $expected->id);

        $this->actingAs($guest)->getJson(route('messages.search', [
            'host_name' => 'Maya Duplicate',
            'property_name' => 'Missing Property',
        ]))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_literal_wildcards_and_pagination_are_safe(): void
    {
        $guest = $this->user('Wildcard Acceptance Guest', 'guest');
        $host = $this->user('Host 100%_', 'host');
        $literal = $this->property($host, 'Place 100%_ 01');
        $this->property($host, 'Place 100AA One');

        foreach (range(2, 21) as $number) {
            $this->property($host, "Place 100%_ {$number}");
        }

        $response = $this->actingAs($guest)->getJson(route('messages.search', [
            'host_name' => '100%_',
            'property_name' => '100%_',
        ]));

        $response->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 21)
            ->assertJsonPath('meta.per_page', 20);
        $this->assertContains($literal->id, collect($response->json('data'))->pluck('property.id')->all());
    }

    public function test_hosts_are_denied_search_even_by_direct_url(): void
    {
        $host = $this->user('Denied Search Host', 'host');

        $this->actingAs($host)->getJson(route('messages.search', [
            'host_name' => 'Host',
            'property_name' => 'Place',
        ]))->assertForbidden();
    }
}
