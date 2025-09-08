<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\EmailSetting;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Log;

#[Layout('layouts.panel')]
class EmailSettingsEdit extends Component
{
    public string $category;
    public string $categoryLabel;
    public array $settings = [];
    public array $formData = [];

    public function mount(string $category)
    {
        $this->category = $category;
        $this->categoryLabel = $this->getCategoryLabels()[$category] ?? ucfirst($category);

        $this->loadSettings();

        if (empty($this->settings)) {
            abort(404, 'Categoría no encontrada');
        }

        // Initialize form data with current values
        foreach ($this->settings as $setting) {
            $this->formData[$setting['key']] = $setting['value'];
        }
    }

    public function getTitle()
    {
        return "Editar {$this->categoryLabel}";
    }

    public function loadSettings()
    {
        $this->settings = EmailSetting::getByCategory($this->category);
    }

    public function updateSettings()
    {
        try {
            $updatedCount = 0;

            foreach ($this->formData as $key => $value) {
                $setting = EmailSetting::where('key', $key)->first();

                if ($setting) {
                    // Cast value to appropriate type
                    $castedValue = $this->castValue($value, $setting->type);

                    if ($setting->value !== $castedValue) {
                        $setting->update(['value' => $castedValue]);
                        $updatedCount++;
                    }
                }
            }

            EmailSetting::clearCache();
            $this->loadSettings();

            if ($updatedCount > 0) {
                session()->flash('success', "Se actualizaron {$updatedCount} configuraciones exitosamente.");
            } else {
                session()->flash('info', 'No se realizaron cambios.');
            }

        } catch (\Exception $e) {
            Log::error('Error updating email settings', [
                'category' => $this->category,
                'error' => $e->getMessage(),
            ]);

            session()->flash('error', 'Error al actualizar las configuraciones: ' . $e->getMessage());
        }
    }

    public function resetCategorySettings()
    {
        try {
            EmailSetting::where('category', $this->category)
                ->update(['is_active' => true]);

            EmailSetting::clearCache();
            $this->loadSettings();

            // Reset form data
            foreach ($this->settings as $setting) {
                $this->formData[$setting['key']] = $setting['value'];
            }

            session()->flash('success', 'Configuraciones de la categoría restablecidas exitosamente.');

        } catch (\Exception $e) {
            Log::error('Error resetting category settings', [
                'category' => $this->category,
                'error' => $e->getMessage(),
            ]);

            session()->flash('error', 'Error al restablecer las configuraciones.');
        }
    }

    public function testCategorySettings()
    {
        // This will be handled by a separate test modal component
        $this->dispatch('open-category-test', category: $this->category);
    }

    private function castValue($value, string $type)
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            'array', 'json' => is_string($value) ? json_decode($value, true) : $value,
            default => (string) $value,
        };
    }

    protected function getCategoryLabels(): array
    {
        return [
            'general' => 'Configuración General',
            'features' => 'Activar/Desactivar Funciones',
            'user_notifications' => 'Notificaciones de Usuario',
            'admin_notifications' => 'Notificaciones Administrativas',
            'schedules' => 'Horarios y Programación',
            'queues' => 'Configuración de Colas',
        ];
    }

    public function render()
    {
        return view('livewire.admin.email-settings-edit')
            ->title($this->getTitle());
    }
}
