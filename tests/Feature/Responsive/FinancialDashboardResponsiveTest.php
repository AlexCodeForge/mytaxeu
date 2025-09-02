<?php

declare(strict_types=1);

namespace Tests\Feature\Responsive;

use App\Livewire\Admin\FinancialDashboard;
use App\Models\User;
use App\Models\CreditTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Laravel\Cashier\Subscription;

class FinancialDashboardResponsiveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_mobile_layout_uses_single_column_grid(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            // Mobile-first approach - single column
            ->assertSeeHtml('grid-cols-1')
            ->assertSeeHtml('space-y-6'); // Vertical spacing for mobile
    }

    public function test_tablet_layout_uses_two_column_grid(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            // Tablet breakpoint
            ->assertSeeHtml('sm:grid-cols-2')
            ->assertSeeHtml('md:flex')
            ->assertSeeHtml('md:items-center');
    }

    public function test_desktop_layout_uses_three_column_grid(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            // Desktop breakpoint
            ->assertSeeHtml('lg:grid-cols-3')
            ->assertSeeHtml('lg:grid-cols-4')
            ->assertSeeHtml('lg:flex-row');
    }

    public function test_chart_containers_are_responsive(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            // Chart containers should be responsive
            ->assertSeeHtml('w-full h-full')
            ->assertSeeHtml('h-64') // Fixed height for charts
            ->assertSeeHtml('flex-col lg:flex-row'); // Stack on mobile, side-by-side on desktop
    }

    public function test_kpi_cards_stack_properly_on_mobile(): void
    {
        $this->actingAs($this->admin);

        // Create some financial data
        CreditTransaction::factory()->create([
            'user_id' => $this->admin->id,
            'type' => 'purchased',
            'amount' => 2500,
        ]);

        Livewire::test(FinancialDashboard::class)
            // KPI cards should stack on mobile
            ->assertSeeHtml('grid-cols-1')
            ->assertSeeHtml('gap-5')
            ->assertSeeHtml('space-y-1') // Vertical spacing inside cards
            ->assertSee('Ingresos Recurrentes Mensuales')
            ->assertSee('Ingresos Totales');
    }

    public function test_header_section_is_responsive(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            // Header should stack on mobile, flex on larger screens
            ->assertSeeHtml('md:flex')
            ->assertSeeHtml('md:items-center')
            ->assertSeeHtml('md:justify-between')
            ->assertSeeHtml('min-w-0 flex-1') // Prevent text overflow
            ->assertSeeHtml('mt-4 flex md:ml-4 md:mt-0'); // Button positioning
    }

    public function test_time_period_selector_adapts_to_screen_size(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            // Time period selector should be responsive
            ->assertSeeHtml('gap-4')
            ->assertSeeHtml('sm:grid-cols-2')
            ->assertSeeHtml('lg:grid-cols-4')
            ->assertSee('Período de Análisis');
    }

    public function test_custom_date_inputs_are_responsive(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->set('timePeriod', 'custom')
            // Date inputs should be responsive
            ->assertSeeHtml('grid-cols-1')
            ->assertSeeHtml('sm:grid-cols-2')
            ->assertSeeHtml('gap-4')
            ->assertSee('Fecha Inicio')
            ->assertSee('Fecha Fin');
    }

    public function test_subscription_chart_layout_is_responsive(): void
    {
        $this->actingAs($this->admin);

        // Create subscription data
        User::factory()->create()->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test',
            'stripe_status' => 'active',
            'stripe_price' => 'price_test',
        ]);

        Livewire::test(FinancialDashboard::class)
            // Subscription chart should stack on mobile, side-by-side on desktop
            ->assertSeeHtml('flex-col lg:flex-row')
            ->assertSeeHtml('lg:items-center')
            ->assertSeeHtml('lg:space-x-6')
            ->assertSeeHtml('h-48 w-48')
            ->assertSeeHtml('mx-auto lg:mx-0'); // Center on mobile, left-align on desktop
    }

    public function test_export_section_buttons_are_responsive(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            // Export buttons should be responsive
            ->assertSeeHtml('flex items-center justify-between')
            ->assertSeeHtml('space-x-2')
            ->assertSee('Exportar Datos')
            ->assertSee('Exportar CSV');
    }

    public function test_loading_states_are_responsive(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->set('loading', true)
            // Loading spinner should be centered and responsive
            ->assertSeeHtml('justify-center')
            ->assertSeeHtml('items-center')
            ->assertSeeHtml('animate-spin')
            ->assertSee('Cargando datos financieros...');
    }

    public function test_error_states_are_responsive(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->set('hasError', true)
            // Error state should be centered and responsive
            ->assertSeeHtml('text-center')
            ->assertSeeHtml('py-8')
            ->assertSeeHtml('mx-auto')
            ->assertSee('Error al cargar los datos financieros');
    }

    public function test_text_sizing_is_responsive(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            // Different text sizes for different elements
            ->assertSeeHtml('text-2xl') // Main heading
            ->assertSeeHtml('sm:text-3xl') // Larger heading on bigger screens
            ->assertSeeHtml('text-sm') // Small text
            ->assertSeeHtml('text-lg') // Medium text
            ->assertSeeHtml('text-3xl'); // Large numbers/values
    }

    public function test_spacing_and_padding_is_responsive(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            // Responsive spacing
            ->assertSeeHtml('space-y-6') // Vertical spacing
            ->assertSeeHtml('gap-5') // Grid gap
            ->assertSeeHtml('p-6') // Padding
            ->assertSeeHtml('px-4') // Horizontal padding
            ->assertSeeHtml('py-5') // Vertical padding
            ->assertSeeHtml('mx-4'); // Horizontal margin
    }

    public function test_chart_type_selector_buttons_are_responsive(): void
    {
        $this->actingAs($this->admin);

        // Create some revenue data to enable charts
        CreditTransaction::factory()->create([
            'user_id' => $this->admin->id,
            'type' => 'purchased',
            'amount' => 1000,
        ]);

        Livewire::test(FinancialDashboard::class)
            // Chart type buttons should be responsive
            ->assertSeeHtml('flex space-x-2')
            ->assertSeeHtml('px-3 py-1')
            ->assertSeeHtml('rounded-md')
            ->assertSeeHtml('text-sm')
            ->assertSee('Línea')
            ->assertSee('Barras');
    }

    public function test_accessibility_features_in_responsive_design(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            // Focus states should work on all screen sizes
            ->assertSeeHtml('focus:outline')
            ->assertSeeHtml('focus-visible:outline')
            ->assertSeeHtml('focus:ring')
            // ARIA attributes for accessibility
            ->assertSeeHtml('aria-')
            // Proper contrast and sizing for touch targets
            ->assertSeeHtml('min-h-')
            ->assertSeeHtml('touch-manipulation');
    }

    public function test_container_max_widths_are_appropriate(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            // Containers should have appropriate max widths
            ->assertSeeHtml('max-w-2xl')
            ->assertSeeHtml('max-w-sm')
            ->assertSeeHtml('w-full')
            // Containers should be centered on larger screens
            ->assertSeeHtml('mx-auto');
    }

    public function test_breakpoint_behavior_for_navigation(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            // Navigation elements should adapt to screen size
            ->assertSeeHtml('md:ml-4')
            ->assertSeeHtml('md:mt-0')
            ->assertSeeHtml('mt-4 flex')
            ->assertSeeHtml('sm:truncate');
    }

    public function test_image_and_icon_responsive_sizing(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            // Icons should have appropriate sizing
            ->assertSeeHtml('h-4 w-4')
            ->assertSeeHtml('h-5 w-5')
            ->assertSeeHtml('h-12 w-12')
            // SVG icons should be properly sized
            ->assertSeeHtml('viewBox="0 0 24 24"');
    }

    public function test_form_elements_are_touch_friendly(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->set('timePeriod', 'custom')
            // Form elements should be touch-friendly
            ->assertSeeHtml('min-h-')
            ->assertSeeHtml('px-3 py-2')
            ->assertSeeHtml('rounded-md')
            // Date inputs should be properly sized
            ->assertSeeHtml('type="date"');
    }

    public function test_overflow_handling_on_small_screens(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            // Text should handle overflow properly
            ->assertSeeHtml('truncate')
            ->assertSeeHtml('overflow-hidden')
            ->assertSeeHtml('text-ellipsis')
            // Containers should handle overflow
            ->assertSeeHtml('overflow-x-auto');
    }

    public function test_grid_layout_adapts_correctly(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            // Main dashboard grid
            ->assertSeeHtml('grid grid-cols-1 gap-6 lg:grid-cols-2')
            // KPI cards grid
            ->assertSeeHtml('grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3')
            // Time period grid
            ->assertSeeHtml('grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4');
    }

    public function test_vertical_spacing_consistency(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            // Consistent vertical spacing throughout
            ->assertSeeHtml('space-y-6')
            ->assertSeeHtml('space-y-3')
            ->assertSeeHtml('space-y-1')
            ->assertSeeHtml('mb-4')
            ->assertSeeHtml('mt-2');
    }

    public function test_hover_and_interaction_states_work_on_touch(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            // Hover states should work on touch devices
            ->assertSeeHtml('hover:bg-indigo-500')
            ->assertSeeHtml('hover:bg-gray-50')
            ->assertSeeHtml('hover:text-gray-200')
            // Active states for touch
            ->assertSeeHtml('active:')
            // Transition for smooth interactions
            ->assertSeeHtml('transition-colors');
    }
}
