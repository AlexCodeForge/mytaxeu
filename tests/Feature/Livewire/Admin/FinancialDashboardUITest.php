<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\FinancialDashboard;
use App\Models\User;
use App\Models\CreditTransaction;
use App\Services\FinancialDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Carbon\Carbon;
use Laravel\Cashier\Subscription;

class FinancialDashboardUITest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->regularUser = User::factory()->create(['is_admin' => false]);
    }

    /** @test */
    public function it_displays_responsive_grid_layout(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->assertSeeHtml('grid-cols-1')
            ->assertSeeHtml('sm:grid-cols-2')
            ->assertSeeHtml('lg:grid-cols-3')
            ->assertSeeHtml('gap-5');
    }

    /** @test */
    public function it_displays_key_performance_indicator_cards(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->assertSee('Ingresos Recurrentes Mensuales')
            ->assertSee('Ingresos Totales')
            ->assertSee('Ingresos del Período')
            ->assertSee('Suscripciones Activas')
            ->assertSee('Ingreso Promedio por Usuario');
    }

    /** @test */
    public function it_formats_currency_values_correctly(): void
    {
        $this->actingAs($this->admin);

        // Create some revenue data
        CreditTransaction::factory()->create([
            'user_id' => $this->regularUser->id,
            'type' => 'purchased',
            'amount' => 2500, // $25.00
        ]);

        Livewire::test(FinancialDashboard::class)
            ->assertSee('$25.00'); // Should format as currency
    }

    /** @test */
    public function it_displays_revenue_growth_indicators(): void
    {
        $this->actingAs($this->admin);

        // Create current month revenue
        CreditTransaction::factory()->create([
            'user_id' => $this->regularUser->id,
            'type' => 'purchased',
            'amount' => 2000, // $20.00
            'created_at' => now()->startOfMonth()->addDays(10),
        ]);

        // Create previous month revenue
        CreditTransaction::factory()->create([
            'user_id' => $this->regularUser->id,
            'type' => 'purchased',
            'amount' => 1000, // $10.00
            'created_at' => now()->subMonth()->startOfMonth()->addDays(10),
        ]);

        Livewire::test(FinancialDashboard::class)
            ->assertSeeHtml('text-green-600') // Positive growth should show green
            ->assertSee('vs período anterior');
    }

    /** @test */
    public function it_shows_negative_growth_with_red_indicator(): void
    {
        $this->actingAs($this->admin);

        // Create current month revenue (lower)
        CreditTransaction::factory()->create([
            'user_id' => $this->regularUser->id,
            'type' => 'purchased',
            'amount' => 1000, // $10.00
            'created_at' => now()->startOfMonth()->addDays(10),
        ]);

        // Create previous month revenue (higher)
        CreditTransaction::factory()->create([
            'user_id' => $this->regularUser->id,
            'type' => 'purchased',
            'amount' => 2000, // $20.00
            'created_at' => now()->subMonth()->startOfMonth()->addDays(10),
        ]);

        Livewire::test(FinancialDashboard::class)
            ->assertSeeHtml('text-red-600'); // Negative growth should show red
    }

    /** @test */
    public function it_displays_time_period_selector_with_all_options(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->assertSee('Período de Análisis')
            ->assertSee('Mensual')
            ->assertSee('Trimestral')
            ->assertSee('Anual')
            ->assertSee('Rango Personalizado');
    }

    /** @test */
    public function it_shows_custom_date_inputs_when_custom_period_selected(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->set('timePeriod', 'custom')
            ->assertSee('Fecha Inicio')
            ->assertSee('Fecha Fin')
            ->assertSeeHtml('type="date"');
    }

    /** @test */
    public function it_hides_custom_date_inputs_for_preset_periods(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->set('timePeriod', 'monthly')
            ->assertDontSee('Fecha Inicio')
            ->assertDontSee('Fecha Fin');
    }

    /** @test */
    public function it_displays_loading_spinner_with_proper_styling(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->set('loading', true)
            ->assertSeeHtml('animate-spin')
            ->assertSee('Cargando datos financieros...')
            ->assertSeeHtml('bg-blue-50');
    }

    /** @test */
    public function it_displays_error_state_with_proper_styling(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->set('hasError', true)
            ->assertSeeHtml('bg-red-50')
            ->assertSeeHtml('text-red-400')
            ->assertSee('Error al cargar los datos financieros');
    }

    /** @test */
    public function it_displays_chart_container_with_proper_dimensions(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->assertSee('Tendencia de Ingresos')
            ->assertSeeHtml('h-64') // Chart container height
            ->assertSeeHtml('bg-gray-50'); // Chart placeholder background
    }

    /** @test */
    public function it_shows_chart_placeholder_when_no_data_available(): void
    {
        $this->actingAs($this->admin);

        // No financial data exists
        Livewire::test(FinancialDashboard::class)
            ->assertSee('No hay datos financieros disponibles')
            ->assertSee('Los gráficos se mostrarán cuando haya datos de ingresos.');
    }

    /** @test */
    public function it_displays_subscription_status_breakdown_with_colors(): void
    {
        $this->actingAs($this->admin);

        // Create subscriptions with different statuses
        User::factory()->create()->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_active',
            'stripe_status' => 'active',
            'stripe_price' => 'price_test',
        ]);

        User::factory()->create()->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_canceled',
            'stripe_status' => 'canceled',
            'stripe_price' => 'price_test',
        ]);

        Livewire::test(FinancialDashboard::class)
            ->assertSee('Estado de Suscripciones')
            ->assertSee('Activas')
            ->assertSee('Canceladas')
            ->assertSeeHtml('bg-green-500') // Active status color
            ->assertSeeHtml('bg-red-500'); // Canceled status color
    }

    /** @test */
    public function it_shows_empty_subscription_state_when_no_subscriptions(): void
    {
        $this->actingAs($this->admin);

        // No subscriptions exist
        Livewire::test(FinancialDashboard::class)
            ->assertSee('No hay datos de suscripciones disponibles');
    }

    /** @test */
    public function it_displays_export_section_with_proper_buttons(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->assertSee('Exportar Datos')
            ->assertSee('Descarga los datos financieros para análisis externo.')
            ->assertSee('Exportar CSV')
            ->assertSeeHtml('wire:click="exportData(\'csv\')"');
    }

    /** @test */
    public function it_triggers_export_action_when_button_clicked(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->call('exportData', 'csv')
            ->assertDispatched('financial-data-exported');
    }

    /** @test */
    public function it_displays_refresh_button_with_loading_states(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->assertSee('Actualizar')
            ->assertSeeHtml('wire:click="refreshData"')
            ->assertSeeHtml('wire:loading.attr="disabled"')
            ->assertSeeHtml('wire:loading.class="opacity-50"');
    }

    /** @test */
    public function it_shows_different_button_text_during_loading(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->assertSeeHtml('wire:loading.remove')
            ->assertSeeHtml('wire:loading')
            ->assertSee('Actualizando...');
    }

    /** @test */
    public function it_updates_period_description_based_on_selection(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(FinancialDashboard::class);

        // Test monthly period
        $component->set('timePeriod', 'monthly')
            ->assertSee('Este mes');

        // Test quarterly period
        $component->set('timePeriod', 'quarterly')
            ->assertSee('Este trimestre');

        // Test yearly period
        $component->set('timePeriod', 'yearly')
            ->assertSee('Este año');

        // Test custom period
        $component->set('timePeriod', 'custom')
            ->assertSee('Rango seleccionado');
    }

    /** @test */
    public function it_displays_financial_cards_with_proper_icons(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->assertSeeHtml('svg') // Icons should be present
            ->assertSeeHtml('h-4 w-4'); // Icon sizing
    }

    /** @test */
    public function it_shows_validation_errors_with_proper_styling(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->set('timePeriod', 'custom')
            ->set('startDate', '2024-02-15')
            ->set('endDate', '2024-01-15') // End before start
            ->call('refreshData')
            ->assertHasErrors(['endDate'])
            ->assertSeeHtml('text-red-600'); // Error text styling
    }

    /** @test */
    public function it_implements_responsive_breakpoints_correctly(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            // Grid responsive classes
            ->assertSeeHtml('grid-cols-1')
            ->assertSeeHtml('sm:grid-cols-2')
            ->assertSeeHtml('lg:grid-cols-3')
            // Flex responsive classes
            ->assertSeeHtml('md:flex')
            ->assertSeeHtml('md:items-center')
            ->assertSeeHtml('md:justify-between')
            // Spacing responsive classes
            ->assertSeeHtml('gap-4')
            ->assertSeeHtml('sm:grid-cols-2')
            ->assertSeeHtml('lg:grid-cols-4');
    }

    /** @test */
    public function it_displays_chart_loading_placeholder(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->assertSee('Gráfico de Tendencia')
            ->assertSee('Chart.js se integrará aquí')
            ->assertSeeHtml('mx-auto h-12 w-12'); // Placeholder icon styling
    }

    /** @test */
    public function it_handles_chart_data_updates_with_alpine(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->assertSeeHtml('@script')
            ->assertSeeHtml('Alpine.data')
            ->assertSeeHtml('financialDashboard');
    }

    /** @test */
    public function it_implements_proper_color_scheme_for_status_indicators(): void
    {
        $this->actingAs($this->admin);

        User::factory()->create()->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_trialing',
            'stripe_status' => 'trialing',
            'stripe_price' => 'price_test',
        ]);

        User::factory()->create()->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_past_due',
            'stripe_status' => 'past_due',
            'stripe_price' => 'price_test',
        ]);

        Livewire::test(FinancialDashboard::class)
            ->assertSeeHtml('bg-blue-500') // Trialing
            ->assertSeeHtml('bg-yellow-500'); // Past due
    }

    /** @test */
    public function it_displays_mobile_friendly_layout(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            // Mobile-first responsive classes
            ->assertSeeHtml('space-y-6') // Vertical spacing for mobile
            ->assertSeeHtml('px-4') // Mobile padding
            ->assertSeeHtml('py-5') // Mobile padding
            ->assertSeeHtml('mx-4') // Mobile margin
            ->assertSeeHtml('w-full') // Full width on mobile
            ->assertSeeHtml('max-w-2xl'); // Constrained width for larger screens
    }

    /** @test */
    public function it_shows_proper_shadow_and_border_styling(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->assertSeeHtml('shadow') // Card shadows
            ->assertSeeHtml('rounded-lg') // Rounded corners
            ->assertSeeHtml('bg-white') // White backgrounds
            ->assertSeeHtml('border'); // Borders where appropriate
    }

    /** @test */
    public function it_implements_accessibility_features(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->assertSeeHtml('aria-')  // ARIA attributes
            ->assertSeeHtml('focus:') // Focus states
            ->assertSeeHtml('focus-visible:'); // Focus-visible states
    }

    /** @test */
    public function it_displays_proper_font_weights_and_sizes(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->assertSeeHtml('text-2xl') // Large headings
            ->assertSeeHtml('text-3xl') // Extra large numbers
            ->assertSeeHtml('font-bold') // Bold headings
            ->assertSeeHtml('font-semibold') // Semi-bold text
            ->assertSeeHtml('font-medium') // Medium weight
            ->assertSeeHtml('text-sm') // Small text
            ->assertSeeHtml('text-gray-500'); // Muted text color
    }

    /** @test */
    public function it_handles_interactive_elements_with_hover_states(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->assertSeeHtml('hover:bg-indigo-500') // Button hover
            ->assertSeeHtml('hover:bg-gray-50') // Card hover
            ->assertSeeHtml('transition-colors'); // Smooth transitions
    }

    /** @test */
    public function it_displays_alpine_js_event_listeners(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->assertSeeHtml('$wire.on')
            ->assertSeeHtml('financial-data-refreshed')
            ->assertSeeHtml('time-period-changed')
            ->assertSeeHtml('chart-data-updated');
    }
}
