<x-filament-widgets::widget>
    <x-filament::section heading="Monthly breakdown — morning / evening">
        @php($rows = $this->getRows())

        @if (empty($rows))
            <p class="text-sm text-gray-500 dark:text-gray-400">No data for this period yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="py-2 pr-4 font-medium">Month</th>
                            <th class="py-2 px-3 font-medium text-right">Ours</th>
                            <th class="py-2 px-3 font-medium text-right">Ours AM</th>
                            <th class="py-2 px-3 font-medium text-right">Ours PM</th>
                            <th class="py-2 px-3 font-medium text-right">Competitors</th>
                            <th class="py-2 px-3 font-medium text-right">Comp. AM</th>
                            <th class="py-2 pl-3 font-medium text-right">Comp. PM</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($rows as $row)
                            @php($fmt = fn ($v) => is_null($v) ? '—' : $v . '%')
                            <tr>
                                <td class="py-2 pr-4 font-medium">{{ $row['month'] }}</td>
                                <td class="py-2 px-3 text-right font-semibold text-green-600 dark:text-green-400">{{ $fmt($row['ours']['occupancy']) }}</td>
                                <td class="py-2 px-3 text-right text-gray-500 dark:text-gray-400">{{ $fmt($row['ours']['am']) }}</td>
                                <td class="py-2 px-3 text-right text-gray-500 dark:text-gray-400">{{ $fmt($row['ours']['pm']) }}</td>
                                <td class="py-2 px-3 text-right font-semibold">{{ $fmt($row['competitors']['occupancy']) }}</td>
                                <td class="py-2 px-3 text-right text-gray-500 dark:text-gray-400">{{ $fmt($row['competitors']['am']) }}</td>
                                <td class="py-2 pl-3 text-right text-gray-500 dark:text-gray-400">{{ $fmt($row['competitors']['pm']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
