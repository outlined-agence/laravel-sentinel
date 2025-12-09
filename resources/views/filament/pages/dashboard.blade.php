<x-filament-panels::page>
    <x-filament-widgets::widgets
        :widgets="$this->getHeaderWidgets()"
        :columns="$this->getHeaderWidgetsColumns()"
    />

    <x-filament-widgets::widgets
        :widgets="$this->getFooterWidgets()"
        :columns="2"
    />
</x-filament-panels::page>
