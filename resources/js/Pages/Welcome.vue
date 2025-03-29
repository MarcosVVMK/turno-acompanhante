<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';

const page = usePage();
const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
});

// Date handling with UTC-3 adjustment
const getLocalDate = () => {
    const date = new Date();
    // Adjust for UTC-3 timezone
    return new Date(date.getTime() - (3 * 60 * 60 * 1000));
};

// Get Monday of current week
const getStartOfWeek = (date) => {
    const day = date.getDay(); // 0 = Sunday, 1 = Monday
    const diff = day === 0 ? 6 : day - 1; // If Sunday, go back 6 days, otherwise day-1
    const monday = new Date(date);
    monday.setDate(date.getDate() - diff);
    return monday;
};

// Get array of 7 days for the week
const getWeekDays = (startDate) => {
    const days = [];
    for (let i = 0; i < 7; i++) {
        const day = new Date(startDate);
        day.setDate(startDate.getDate() + i);
        days.push(day);
    }
    return days;
};

// Format date for input field (YYYY-MM-DD)
function formatDateForInput(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

// Format date as DD/MM/YYYY
function formatDateDisplay(date) {
    if (typeof date === 'string') {
        date = new Date(date);
    }
    return `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}/${date.getFullYear()}`;
}

// Get day name in Portuguese
function getDayOfWeek(date) {
    const days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    return days[date.getDay()];
}

// Initialize dates
const today = getLocalDate();
const currentWeekStart = getStartOfWeek(today);
const selectedWeekStart = ref(formatDateForInput(currentWeekStart));
const weekDays = ref(getWeekDays(currentWeekStart));
const isLoading = ref(false);
const isSaving = ref(false);

// Shifts definition
const shiftTypes = [
    { label: 'Manhã', time: '07h - 13h' },
    { label: 'Tarde', time: '13h - 19h' },
    { label: 'Noite', time: '19h - 07h' },
];

// Data structure to store all shifts for the week
// Format: { 'YYYY-MM-DD': { 'Manhã': ['User1', 'User2'], 'Tarde': [...], 'Noite': [...] } }
const weekShifts = ref({});

// Pending changes to be saved
// Format: [{ date: 'YYYY-MM-DD', shift: 'Manhã', action: 'add'|'remove' }]
const pendingChanges = ref([]);

// Initialize weekShifts with empty arrays for all days
const initializeWeekShifts = () => {
    const shifts = {};
    weekDays.value.forEach(day => {
        const dateKey = formatDateForInput(day);
        shifts[dateKey] = {};
        shiftTypes.forEach(shift => {
            shifts[dateKey][shift.label] = [];
        });
    });
    weekShifts.value = shifts;
};

// Load existing shifts from the database
const loadShifts = async () => {
    if (!page.props.auth.user) return;

    isLoading.value = true;
    const startDate = selectedWeekStart.value;
    const endDate = formatDateForInput(new Date(new Date(startDate).setDate(new Date(startDate).getDate() + 6)));

    try {
        const response = await axios.get('/api/shifts', {
            params: { start_date: startDate, end_date: endDate }
        });

        // Initialize empty shifts structure
        initializeWeekShifts();

        // Populate with data from server
        if (response.data.shifts) {
            response.data.shifts.forEach(shift => {
                if (weekShifts.value[shift.date] &&
                    weekShifts.value[shift.date][shift.shift]) {
                    weekShifts.value[shift.date][shift.shift].push(shift.user.name);
                }
            });
        }

        // Clear pending changes after loading new data
        pendingChanges.value = [];
    } catch (error) {
        console.error('Failed to load shifts:', error);
    } finally {
        isLoading.value = false;
    }
};

// Initialize on component mount
onMounted(() => {
    initializeWeekShifts();
    loadShifts();
});

// Watch for week changes
watch(selectedWeekStart, () => {
    loadShifts();
});

// Form for API submissions
const form = useForm({
    shifts: []
});

// Week navigation
const previousWeek = () => {
    const prevWeekStart = new Date(selectedWeekStart.value);
    prevWeekStart.setDate(prevWeekStart.getDate() - 7);
    selectedWeekStart.value = formatDateForInput(prevWeekStart);
    weekDays.value = getWeekDays(prevWeekStart);
};

const nextWeek = () => {
    const nextWeekStart = new Date(selectedWeekStart.value);
    nextWeekStart.setDate(nextWeekStart.getDate() + 7);
    selectedWeekStart.value = formatDateForInput(nextWeekStart);
    weekDays.value = getWeekDays(nextWeekStart);
};

const handleWeekChange = (event) => {
    const date = new Date(event.target.value);
    const newWeekStart = getStartOfWeek(date);
    selectedWeekStart.value = formatDateForInput(newWeekStart);
    weekDays.value = getWeekDays(newWeekStart);
};

// Check if date is today
const isToday = (date) => {
    return date.getDate() === today.getDate() &&
        date.getMonth() === today.getMonth() &&
        date.getFullYear() === today.getFullYear();
};

// Toggle shift selection (add to pending changes)
const toggleShift = (date, shift) => {
    const currentUser = page.props.auth.user;
    if (!currentUser) return;

    const dateKey = formatDateForInput(date);
    const users = weekShifts.value[dateKey][shift.label];
    const userIndex = users.indexOf(currentUser.name);

    // Add a pending change
    const existingChangeIndex = pendingChanges.value.findIndex(
        change => change.date === dateKey && change.shift === shift.label
    );

    if (existingChangeIndex !== -1) {
        // Remove the existing change if it exists (toggle back)
        pendingChanges.value.splice(existingChangeIndex, 1);
    } else {
        // Add a new pending change
        pendingChanges.value.push({
            date: dateKey,
            shift: shift.label,
            action: userIndex === -1 ? 'add' : 'remove'
        });
    }

    // Apply the change locally for UI feedback
    if (userIndex === -1) {
        // Add user to shift in UI
        users.push(currentUser.name);
    } else {
        // Remove user from shift in UI
        users.splice(userIndex, 1);
    }
};

// Save all pending changes
const saveChanges = () => {
    if (pendingChanges.value.length === 0) return;

    isSaving.value = true;

    form.shifts = pendingChanges.value;
    form.post(route('shifts.batch-store'), {
        onSuccess: () => {
            // Clear pending changes after successful save
            pendingChanges.value = [];
            isSaving.value = false;

            // Reload shifts to get the updated data
            loadShifts();
        },
        onError: () => {
            isSaving.value = false;
        }
    });
};

// Check if user is in shift
const isUserInShift = (date, shift) => {
    const currentUser = page.props.auth.user;
    if (!currentUser) return false;

    const dateKey = formatDateForInput(date);
    if (!weekShifts.value[dateKey]) return false;

    return weekShifts.value[dateKey][shift.label].includes(currentUser.name);
};

// Check if there are pending changes
const hasPendingChanges = computed(() => {
    return pendingChanges.value.length > 0;
});
</script>

<template>
    <Head title="Welcome" />

    <div
        class="relative min-h-screen bg-gray-100 bg-center selection:bg-[#FF2D20] selection:text-white dark:bg-gray-900"
    >
        <div v-if="canLogin" class="fixed top-0 right-0 p-6 text-end">
            <Link
                v-if="$page.props.auth.user"
                :href="route('dashboard')"
                class="font-semibold text-gray-600 hover:text-gray-900 focus:outline focus:outline-2 focus:rounded-sm focus:outline-[#FF2D20] dark:text-gray-400 dark:hover:text-white"
            >Dashboard</Link>

            <template v-else>
                <Link
                    :href="route('login')"
                    class="font-semibold text-gray-600 hover:text-gray-900 focus:outline focus:outline-2 focus:rounded-sm focus:outline-[#FF2D20] dark:text-gray-400 dark:hover:text-white"
                >Log in</Link>
            </template>
        </div>

        <div class="p-6 mx-auto max-w-7xl lg:p-8">
            <main>
                <!-- Weekly Calendar Component -->
                <div class="p-6 bg-white shadow rounded-lg dark:bg-gray-800">
                    <h2 class="text-xl font-bold text-gray-700 dark:text-white text-center">
                        Calendário Semanal de Turnos
                    </h2>

                    <!-- Week Selection Controls -->
                    <div class="mt-4 flex flex-col items-center">
                        <label for="week-picker" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 text-center">
                            Selecione a semana:
                        </label>

                        <div class="flex items-center space-x-2">
                            <button
                                @click="previousWeek"
                                class="px-3 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-white"
                                :disabled="isLoading"
                            >
                                &lt; Anterior
                            </button>

                            <div class="relative" @click="$refs.weekPicker.showPicker ? $refs.weekPicker.showPicker() : $refs.weekPicker.click()">
                                <input
                                    ref="weekPicker"
                                    type="date"
                                    id="week-picker"
                                    v-model="selectedWeekStart"
                                    @change="handleWeekChange"
                                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white cursor-pointer"
                                    :disabled="isLoading"
                                />
                                <div class="absolute inset-0 cursor-pointer" @click.stop></div>
                            </div>

                            <button
                                @click="nextWeek"
                                class="px-3 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-white"
                                :disabled="isLoading"
                            >
                                Próxima &gt;
                            </button>
                        </div>
                    </div>

                    <!-- Loading Indicator -->
                    <div v-if="isLoading" class="text-center py-4">
                        <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-current border-r-transparent align-[-0.125em] motion-reduce:animate-[spin_1.5s_linear_infinite]" role="status">
                            <span class="!absolute !-m-px !h-px !w-px !overflow-hidden !whitespace-nowrap !border-0 !p-0 ![clip:rect(0,0,0,0)]">Loading...</span>
                        </div>
                        <p class="mt-2 text-gray-600 dark:text-gray-300">Carregando turnos...</p>
                    </div>

                    <!-- Week Calendar Table -->
                    <div v-if="!isLoading" class="mt-6 overflow-x-auto">
                        <table class="min-w-full border-collapse">
                            <thead>
                            <tr>
                                <th class="p-2 border bg-gray-50 dark:bg-gray-700 dark:text-white text-center">Turno</th>
                                <th
                                    v-for="(day, index) in weekDays"
                                    :key="index"
                                    class="p-2 border bg-gray-50 dark:bg-gray-700 dark:text-white text-center"
                                    :class="{'bg-blue-50 dark:bg-blue-900': isToday(day)}"
                                >
                                    <div>{{ getDayOfWeek(day) }}</div>
                                    <div class="text-xs">{{ formatDateDisplay(day) }}</div>
                                </th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(shift, shiftIndex) in shiftTypes" :key="shiftIndex">
                                <td class="p-2 border bg-gray-50 dark:bg-gray-700 dark:text-white">
                                    <div class="font-medium">{{ shift.label }}</div>
                                    <div class="text-xs">{{ shift.time }}</div>
                                </td>
                                <td
                                    v-for="(day, dayIndex) in weekDays"
                                    :key="dayIndex"
                                    class="p-2 border text-center align-top cursor-pointer h-20"
                                    :class="{
                                            'bg-blue-50 dark:bg-blue-900/20': isToday(day),
                                            'bg-blue-500 text-white hover:bg-blue-600': isUserInShift(day, shift),
                                            'hover:bg-gray-100 dark:hover:bg-gray-700': !isUserInShift(day, shift)
                                        }"
                                    @click="toggleShift(day, shift)"
                                >
                                    <div v-if="weekShifts[formatDateForInput(day)]">
                                        <div
                                            v-for="(user, userIndex) in weekShifts[formatDateForInput(day)][shift.label]"
                                            :key="userIndex"
                                            class="text-sm py-1"
                                        >
                                            {{ user }}
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Save Button -->
                    <div class="mt-6 flex justify-center">
                        <button
                            @click="saveChanges"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="!hasPendingChanges || isSaving"
                        >
                            <span v-if="isSaving">
                                <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-solid border-current border-r-transparent align-[-0.125em] motion-reduce:animate-[spin_1.5s_linear_infinite] mr-2"></span>
                                Salvando...
                            </span>
                            <span v-else>
                                Salvar Alterações
                                <span v-if="hasPendingChanges" class="ml-2 bg-white text-blue-600 rounded-full px-2 py-1 text-xs">
                                    {{ pendingChanges.length }}
                                </span>
                            </span>
                        </button>
                    </div>
                </div>
            </main>

            <footer class="py-16 text-center text-sm text-black dark:text-white/70">
                Laravel v{{ laravelVersion }} (PHP v{{ phpVersion }})
            </footer>
        </div>
    </div>
</template>
