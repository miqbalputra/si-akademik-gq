<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDashboardLivewireTest extends TestCase
{
    use RefreshDatabase;

    public static function dashboardWidgets(): array
    {
        return [
            'admin school statistics' => ['admin', 'school-overview-stats', 'Ringkasan Sekolah'],
            'admin diniyyah statistics' => ['admin', 'diniyyah-workflow-stats', 'Alur Diniyyah'],
            'admin tahfidz statistics' => ['admin', 'tahfidz-workflow-stats', 'Koordinasi Tahfidz'],
            'kabag diniyyah statistics' => ['kabag_diniyyah', 'diniyyah-workflow-stats', 'Alur Diniyyah'],
            'kabag tahfidz statistics' => ['kabag_tahfidz', 'tahfidz-workflow-stats', 'Koordinasi Tahfidz'],
            'kepala sekolah statistics' => ['kepala_sekolah', 'school-overview-stats', 'Ringkasan Sekolah'],
        ];
    }

    #[DataProvider('dashboardWidgets')]
    public function test_dashboard_widgets_can_lazy_load_and_poll_on_the_admin_guard(string $role, string $widget, string $heading): void
    {
        $this->actingAs($this->userWithRole($role), 'admin');
        $component = $this->widgetFromDashboard($widget);

        // Real HTTP updates replay persistent middleware. Livewire::test()
        // alone does not reproduce the browser's guard changes between requests.
        $loaded = $this->updateWidget($component);
        $this->assertSame(200, $loaded->status(), 'Lazy load failed.');
        $this->assertStringContainsString($heading, $loaded->json('components.0.effects.html'));
        $this->assertSame('web', auth()->getDefaultDriver());
        $this->assertGuest('web');

        $component['snapshot'] = $loaded->json('components.0.snapshot');
        $component['calls'] = [];
        for ($poll = 0; $poll < 2; $poll++) {
            $response = $this->updateWidget($component);
            $this->assertSame(200, $response->status(), "Poll {$poll} failed.");
            $this->assertStringContainsString($heading, $response->json('components.0.effects.html'));
            $component['snapshot'] = $response->json('components.0.snapshot');
        }
    }

    public function test_expired_admin_session_cannot_continue_reading_a_dashboard_widget(): void
    {
        $this->actingAs($this->userWithRole('admin'), 'admin');
        $component = $this->widgetFromDashboard('school-overview-stats');
        auth('admin')->logout();

        $this->updateWidget($component)->assertUnauthorized();
    }

    public function test_panel_role_is_rechecked_on_follow_up_widget_requests(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin, 'admin');
        $component = $this->widgetFromDashboard('school-overview-stats');
        $admin->removeRole('admin');

        $this->updateWidget($component)->assertForbidden();
    }

    private function widgetFromDashboard(string $widget): array
    {
        $page = $this->get('/admin')->assertOk();
        $document = new DOMDocument;
        $document->loadHTML($page->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $names = [];

        foreach ($document->getElementsByTagName('*') as $element) {
            if (! $element->hasAttribute('wire:snapshot')) {
                continue;
            }
            $snapshot = $element->getAttribute('wire:snapshot');
            $memo = json_decode($snapshot, true)['memo'];
            $names[] = $memo['name'];
            if (Str::kebab(class_basename($memo['name'])) !== $widget) {
                continue;
            }
            $this->assertSame('admin', $memo['path']);
            $this->assertSame(1, preg_match("/__lazyLoad\\('([^']+)'\\)/", $element->getAttribute('x-intersect'), $match));

            return [
                'snapshot' => $snapshot,
                'updates' => [],
                'calls' => [['path' => '', 'method' => '__lazyLoad', 'params' => [$match[1]]]],
            ];
        }

        $this->fail("Widget {$widget} was not found in the dashboard response: ".implode(', ', $names));
    }

    private function updateWidget(array $component): TestResponse
    {
        // Match Livewire's own subsequent-render tests: discard per-request
        // middleware/component caches while keeping the signed browser snapshot.
        app('livewire')->flushState();

        return $this->postJson(app(HandleRequests::class)->getUpdateUri(), ['components' => [$component]], ['X-Livewire' => 'true']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

        return $user;
    }
}
