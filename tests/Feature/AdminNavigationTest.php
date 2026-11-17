<?php

namespace Tests\Feature;

use App\Filament\Resources\DiniyyahClassSubjects\DiniyyahClassSubjectResource;
use App\Filament\Resources\ReportCards\ReportCardResource;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\School;
use App\Models\User;
use App\Support\AdminNavigation;
use Database\Seeders\RolePermissionSeeder;
use DOMDocument;
use DOMXPath;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return [
            'admin' => ['admin', AdminNavigation::GROUPS],
            'kabag diniyyah' => ['kabag_diniyyah', ['Rapor', 'Data Sekolah', 'Diniyyah']],
            'kabag tahfidz' => ['kabag_tahfidz', ['Tahfidz']],
            'kepala sekolah' => ['kepala_sekolah', ['Rapor', 'Data Sekolah', 'Diniyyah', 'Tahfidz']],
        ];
    }

    #[DataProvider('roles')]
    public function test_navigation_is_complete_ordered_and_uses_existing_permissions(string $role, array $expectedGroups): void
    {
        $user = $this->user($role);
        $response = $this->actingAs($user, 'admin')->get('/admin')->assertOk()->assertSee('Cari menu');
        $document = new DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $groups = array_map(fn ($node) => $node->getAttribute('data-gq-nav-group'), iterator_to_array($xpath->query('//*[@data-gq-nav-group]')));
        $this->assertSame($expectedGroups, $groups);
        $keys = array_map(fn ($node) => $node->getAttribute('data-gq-nav-key'), iterator_to_array($xpath->query('//*[@data-gq-nav-key]')));
        $this->assertCount(count(array_unique($keys)), $keys);
        $this->assertNotContains('DiniyyahLedgerSnapshotResource', $keys);
        $this->assertNotContains('DataReadinessAudit', $keys);
        $this->assertNotContains('DemoFlow', $keys);
        $this->assertSame($role === 'admin', in_array('class-rankings', $keys));
        $this->assertSame(in_array($role, ['admin', 'kabag_tahfidz']), in_array('tasmi-report', $keys));

        auth()->shouldUse('admin');
        $navigation = app(AdminNavigation::class);
        foreach ([...Filament::getPanel('admin')->getResources(), ...Filament::getPanel('admin')->getPages()] as $source) {
            if ($source::shouldRegisterNavigation() && $source::canAccess()) {
                $key = $source === Dashboard::class ? 'dashboard' : class_basename($source);
                $this->assertContains($key, $keys, "Missing authorized page: {$source}");
            }
        }
        foreach ($navigation->definitions() as $key => $definition) {
            $this->assertSame($navigation->canAccess($key, $user), in_array($key, $keys), "Visibility mismatch for {$key}");
            $this->assertContains('GET', Route::getRoutes()->match(Request::create($navigation->url($key)))->methods());
        }

        if ($role === 'admin' && getenv('GQ_NAV_PREVIEW_DIR')) {
            file_put_contents(getenv('GQ_NAV_PREVIEW_DIR').'/admin.html', $response->getContent());
        }
    }

    public function test_active_group_is_open_and_rapor_contains_the_correct_destinations(): void
    {
        $this->actingAs($this->user('admin'), 'admin')->get(DiniyyahClassSubjectResource::getUrl())->assertOk()
            ->assertSee('data-gq-nav-group="Diniyyah" data-gq-nav-default-collapsed="false"', false);
        $response = $this->get(ReportCardResource::getUrl())->assertOk();
        $document = new DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $rapor = $xpath->query('//*[@data-gq-nav-group="Rapor"]//*[@data-gq-nav-key]');
        $this->assertSame(['ReportCardResource', 'class-rankings'], array_map(fn ($node) => $node->getAttribute('data-gq-nav-key'), iterator_to_array($rapor)));
    }

    public function test_every_admin_menu_destination_opens_without_a_server_error_or_login_loop(): void
    {
        $this->withoutExceptionHandling();
        $this->seed(RolePermissionSeeder::class);
        $this->actingAs($this->user('admin'), 'admin');
        $school = School::create(['name' => 'Sekolah Pengujian']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil', 'is_active' => true, 'starts_at' => '2026-07-01', 'ends_at' => '2026-12-31']);
        $navigation = app(AdminNavigation::class);
        foreach ($navigation->definitions() as $key => $definition) {
            $response = $this->get($navigation->url($key));
            $this->assertContains($response->status(), [200, 302, 303], "Menu {$key} failed with HTTP {$response->status()}");
            $this->assertNotSame(route('login'), $response->headers->get('Location'), "Menu {$key} returned to login");
        }
    }

    public static function panelDestinations(): array
    {
        return [
            'diniyyah assignment' => ['kabag_diniyyah', 'DiniyyahClassSubjectResource'],
            'diniyyah report cards' => ['kabag_diniyyah', 'ReportCardResource'],
            'tahfidz halaqah' => ['kabag_tahfidz', 'TahfidzHalaqahResource'],
            'tahfidz report' => ['kabag_tahfidz', 'tasmi-report'],
            'read only school data' => ['kepala_sekolah', 'StudentResource'],
        ];
    }

    #[DataProvider('panelDestinations')]
    public function test_portal_to_panel_and_back_does_not_require_another_login(string $role, string $destination): void
    {
        $user = $this->user($role);
        $url = app(AdminNavigation::class)->url($destination);
        $this->actingAs($user, 'web')->post(route('workspace.open-panel'), ['destination' => $destination])->assertRedirect($url);
        $this->assertGuest('web');
        $this->assertAuthenticatedAs($user, 'admin');
        $this->get($url)->assertOk();
        if ($role === 'kepala_sekolah') {
            return;
        }
        $this->post(route('workspace.open-portal'), ['workspace' => $role])
            ->assertRedirect(route($role === 'kabag_diniyyah' ? 'kabag-diniyyah.dashboard' : 'kabag-tahfidz.dashboard'));
        $this->assertGuest('admin');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_transition_rejects_unowned_roles_unregistered_urls_and_cross_department_destinations(): void
    {
        $user = $this->user('kabag_tahfidz');
        $this->actingAs($user, 'web')->post(route('workspace.open-panel'), ['destination' => 'ReportCardResource'])->assertForbidden();
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertGuest('admin');
        $this->postJson(route('workspace.open-panel'), ['destination' => 'https://example.com'])->assertUnprocessable();
        $this->post(route('workspace.open-panel'), ['destination' => 'UserResource'])->assertForbidden();
        $this->post(route('workspace.open-panel'), ['destination' => 'dashboard'])->assertRedirect('/admin');
        $this->postJson(route('workspace.open-portal'), ['workspace' => 'guru'])->assertUnprocessable();
        $this->assertAuthenticatedAs($user, 'admin');
        $this->assertGuest('web');
        $this->get(route('workspace.open-panel'))->assertMethodNotAllowed();
        $this->get(route('workspace.open-portal'))->assertMethodNotAllowed();
    }

    public function test_teacher_cannot_enter_the_management_panel_through_a_transition(): void
    {
        $teacher = $this->user('guru');
        $this->actingAs($teacher)->post(route('workspace.open-panel'), ['destination' => 'dashboard'])->assertForbidden();
        $this->get('/admin')->assertRedirect(route('login'));
        $this->assertGuest('admin');
    }

    public function test_kabag_shortcuts_use_csrf_protected_transitions_and_no_disabled_ledger_link(): void
    {
        $user = $this->user('kabag_diniyyah');
        $response = $this->actingAs($user)->get(route('kabag-diniyyah.dashboard'))->assertOk()
            ->assertSee('action="'.route('workspace.open-panel').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('value="ReportCardResource"', false)
            ->assertDontSee('/admin/diniyyah-ledger-snapshots', false);
        if (getenv('GQ_NAV_PREVIEW_DIR')) {
            file_put_contents(getenv('GQ_NAV_PREVIEW_DIR').'/kabag.html', $response->getContent());
        }
    }

    public function test_management_report_shell_uses_management_logout_and_notifications(): void
    {
        $this->actingAs($this->user('kabag_tahfidz'), 'admin')->get(route('admin.tasmi-report.index'))->assertOk()
            ->assertSee('action="'.route('filament.admin.auth.logout').'"', false)
            ->assertSee('data-feed-url="'.route('admin.notifications.feed').'"', false)
            ->assertSee('action="'.route('workspace.open-portal').'"', false);
    }

    public function test_filament_account_menu_can_post_to_an_owned_portal_using_its_action_url(): void
    {
        $user = $this->user('admin');
        $user->assignRole(Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']));
        $url = route('workspace.open-portal', ['workspace' => 'guru']);
        $this->actingAs($user, 'admin')->get('/admin')->assertOk()->assertSee($url, false);
        $this->post($url)->assertRedirect(route('guru.dashboard'));
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertGuest('admin');
    }

    public function test_transition_requires_login_and_is_not_a_get_side_effect(): void
    {
        $this->post(route('workspace.open-panel'), ['destination' => 'dashboard'])->assertRedirect(route('login'));
        $this->post(route('workspace.open-portal'), ['workspace' => 'guru'])->assertRedirect(route('login'));
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['name' => 'Akun Pengujian '.$role]);
        $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

        return $user;
    }
}
