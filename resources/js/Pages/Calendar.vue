<template>
    <div class="p-6 bg-white shadow rounded-lg">
        <h2 class="text-xl font-bold text-gray-700">Calendário de Turnos</h2>
        <div class="mt-4">
            <p class="text-gray-600">Selecione um turno para o dia {{ today }}</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                <button
                    v-for="(shift, index) in shifts"
                    :key="index"
                    @click="selectShift(shift)"
                    class="p-4 rounded-lg shadow border border-gray-300 hover:bg-gray-100"
                    :class="{'bg-blue-500 text-white': selectedShift === shift.label}"
                >
                    {{ shift.label }} ({{ shift.time }})
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed } from 'vue';
import { usePage, useForm } from '@inertiajs/vue3';

export default {
    setup() {
        const today = computed(() => new Date().toLocaleDateString());
        const shifts = ref([
            { label: 'Manhã', time: '07h - 13h' },
            { label: 'Tarde', time: '13h - 19h' },
            { label: 'Noite', time: '19h - 07h' },
        ]);
        const selectedShift = ref(null);
        const form = useForm({ shift: null });

        const selectShift = (shift) => {
            selectedShift.value = shift.label;
            form.shift = shift.label;
            form.post(route('shifts.store'));
        };

        return { today, shifts, selectedShift, selectShift };
    }
};
</script>
