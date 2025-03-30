<script setup>
import {Head, Link, useForm, usePage} from '@inertiajs/vue3';
import {ref, computed, onMounted, watch} from 'vue';
import axios from 'axios';

const page = usePage();
const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
});

const getLocalDate = () => {
    const date = new Date();
    return new Date(date.toLocaleString('en-US', {timeZone: 'America/Sao_Paulo'}));
};

const getStartOfWeek = (date) => {
    const day = date.getDay();
    const diff = day === 0 ? 6 : day - 1;
    const monday = new Date(date);
    monday.setDate(date.getDate() - diff);
    return monday;
};

const getWeekDays = (startDate) => {
    const days = [];
    for (let i = 0; i < 7; i++) {
        const day = new Date(startDate);
        day.setDate(day.getDate() + i);
        days.push(day);
    }
    return days;
};

function formatDateForInput(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function formatDateDisplay(date) {
    if (typeof date === 'string') {
        date = new Date(date);
    }
    return `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}/${date.getFullYear()}`;
}

function getDayOfWeek(date) {
    const days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    return days[date.getDay()];
}

const today = ref(getLocalDate());
const selectedWeekStart = ref('');
const weekDays = ref([]);
const isLoading = ref(false);
const isSaving = ref(false);
const allUsers = ref([]);
const showUserSelector = ref(false);
const selectedShiftInfo = ref(null);
const weekShifts = ref({});
const pendingChanges = ref([]);

const loadUsers = async () => {
    try {
        const response = await axios.get('/api/users');
        allUsers.value = response.data;
    } catch (error) {
        console.error('Falha ao carregar usuários:', error);
    }
};

const resetToCurrentWeek = () => {
    today.value = getLocalDate();
    const currentWeekStart = getStartOfWeek(today.value);
    selectedWeekStart.value = formatDateForInput(currentWeekStart);
    weekDays.value = getWeekDays(currentWeekStart);
};

const shiftTypes = [
    {label: 'Manhã', time: '07h - 13h'},
    {label: 'Tarde', time: '13h - 19h'},
    {label: 'Noite', time: '19h - 07h'},
];

const initializeWeekShifts = () => {
    const shifts = {};

    if (!weekDays.value || weekDays.value.length === 0) {
        return;
    }

    weekDays.value.forEach(day => {
        const dateKey = formatDateForInput(day);
        shifts[dateKey] = {};
        shiftTypes.forEach(shift => {
            shifts[dateKey][shift.label] = [];
        });
    });
    weekShifts.value = shifts;
};

const loadShifts = async () => {
    isLoading.value = true;
    const startDate = selectedWeekStart.value;
    const endDate = formatDateForInput(new Date(new Date(startDate).setDate(new Date(startDate).getDate() + 7)));

    try {
        const response = await axios.get('/api/shifts', {
            params: {start_date: startDate, end_date: endDate}
        });

        initializeWeekShifts();

        if (response.data.shifts) {
            response.data.shifts.forEach(shift => {
                if (weekShifts.value[shift.date] &&
                    weekShifts.value[shift.date][shift.shift]) {
                    weekShifts.value[shift.date][shift.shift].push({
                        name: shift.user.name,
                        id: shift.user.id
                    });
                }
            });
        }

        pendingChanges.value = [];
    } catch (error) {
        console.error('Failed to load shifts:', error);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    resetToCurrentWeek();
    initializeWeekShifts();
    loadShifts();
    loadUsers();
});

watch(selectedWeekStart, () => {
    loadShifts();
});

const form = useForm({
    shifts: []
});

const previousWeek = () => {
    const prevWeekStart = new Date(selectedWeekStart.value);
    prevWeekStart.setDate(prevWeekStart.getDate() - 6);
    selectedWeekStart.value = formatDateForInput(prevWeekStart);
    weekDays.value = getWeekDays(prevWeekStart);
};

const nextWeek = () => {
    const nextWeekStart = new Date(selectedWeekStart.value);
    nextWeekStart.setDate(nextWeekStart.getDate() + 8);
    selectedWeekStart.value = formatDateForInput(nextWeekStart);
    weekDays.value = getWeekDays(nextWeekStart);
};

const isToday = (date) => {
    return date.getDate() === today.value.getDate() &&
        date.getMonth() === today.value.getMonth() &&
        date.getFullYear() === today.value.getFullYear();
};

const toggleShift = (date, shift) => {
    if (!page.props.auth.user) return;

    selectedShiftInfo.value = {
        date: date,
        shift: shift.label,
        dateKey: formatDateForInput(date)
    };

    showUserSelector.value = true;
};

const assignUserToShift = (user) => {
    if (!selectedShiftInfo.value) return;

    const { dateKey, shift } = selectedShiftInfo.value;
    const users = weekShifts.value[dateKey][shift];

    const userIndex = users.findIndex(u => {
        if (typeof u === 'object') {
            return u.id === user.id;
        }
        return u === user.name;
    });

    if (userIndex === -1) {
        if (users.length > 0) {
            const existingUser = users[0];
            const existingUserId = typeof existingUser === 'object'
                ? existingUser.id
                : allUsers.value.find(u => u.name === existingUser)?.id;

            if (existingUserId) {
                pendingChanges.value.push({
                    date: dateKey,
                    shift: shift,
                    action: 'remove',
                    user_id: existingUserId
                });
            }

            users.length = 0;
        }

        users.push({
            name: user.name,
            id: user.id
        });

        pendingChanges.value.push({
            date: dateKey,
            shift: shift,
            action: 'add',
            user_id: user.id
        });
    } else {
        users.splice(userIndex, 1);

        pendingChanges.value.push({
            date: dateKey,
            shift: shift,
            action: 'remove',
            user_id: user.id
        });
    }

    showUserSelector.value = false;
};

const saveChanges = () => {
    if (pendingChanges.value.length === 0) return;

    isSaving.value = true;
    form.shifts = pendingChanges.value;
    form.post(route('shifts.batch-store'), {
        onSuccess: () => {
            pendingChanges.value = [];
            isSaving.value = false;
            loadShifts();
        },
        onError: () => {
            isSaving.value = false;
        }
    });
};

const isUserInShift = (date, shift) => {
    const currentUser = page.props.auth.user;
    if (!currentUser) return false;

    const dateKey = formatDateForInput(date);
    if (!weekShifts.value[dateKey]) return false;

    return weekShifts.value[dateKey][shift.label].some(u => {
        return typeof u === 'object' ? u.name === currentUser.name : u === currentUser.name;
    });
};

const hasPendingChanges = computed(() => {
    return pendingChanges.value.length > 0;
});

const isUserAssignedToSelectedShift = (user) => {
    if (!selectedShiftInfo.value) return false;

    const { dateKey, shift } = selectedShiftInfo.value;
    if (!weekShifts.value[dateKey]) return false;

    return weekShifts.value[dateKey][shift].some(u => {
        if (typeof u === 'object') {
            return u.id === user.id;
        }
        const existingUser = allUsers.value.find(allUser => allUser.name === u);
        return existingUser && existingUser.id === user.id;
    });
};

</script>
<template>
    <Head title="Welcome"/>

    <div
        class="relative min-h-screen bg-gray-100 bg-center selection:bg-[#FF2D20] selection:text-white dark:bg-gray-900">
        <div v-if="canLogin" class="fixed top-0 right-0 p-6 text-end">
            <Link
                v-if="$page.props.auth.user"
                :href="route('dashboard')"
                class="font-semibold text-gray-600 hover:text-gray-900 focus:outline focus:outline-2 focus:rounded-sm focus:outline-[#FF2D20] dark:text-gray-400 dark:hover:text-white"
            >Dashboard
            </Link>

            <template v-else>
                <Link
                    :href="route('login')"
                    class="font-semibold text-gray-600 hover:text-gray-900 focus:outline focus:outline-2 focus:rounded-sm focus:outline-[#FF2D20] dark:text-gray-400 dark:hover:text-white"
                >Log in
                </Link>
            </template>
        </div>

        <div class="p-6 mx-auto max-w-7xl lg:p-8">
            <main>
                <div class="p-6 bg-white shadow rounded-lg dark:bg-gray-800">
                    <h2 class="text-xl font-bold text-gray-700 dark:text-white text-center">
                        Calendário Semanal de Turnos
                    </h2>

                    <div class="mt-4">
                        <div class="flex items-center justify-between">
                            <button
                                @click="previousWeek"
                                class="px-3 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-white"
                                :disabled="isLoading"
                            >
                                &lt; Anterior
                            </button>

                            <div class="text-center font-medium text-gray-700 dark:text-gray-300">
                                {{ formatDateDisplay(new Date(selectedWeekStart)) }} -
                                {{ formatDateDisplay(weekDays[6] || new Date(selectedWeekStart)) }}
                            </div>

                            <button
                                @click="nextWeek"
                                class="px-3 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-white"
                                :disabled="isLoading"
                            >
                                Próxima &gt;
                            </button>
                        </div>

                        <div class="mt-2 text-center">
                            <button
                                @click="resetToCurrentWeek"
                                class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                :disabled="isLoading"
                            >
                                Voltar para semana atual
                            </button>
                        </div>
                    </div>

                    <div v-if="isLoading" class="text-center py-4">
                        <div
                            class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-current border-r-transparent align-[-0.125em] motion-reduce:animate-[spin_1.5s_linear_infinite]"
                            role="status">
                            <span
                                class="!absolute !-m-px !h-px !w-px !overflow-hidden !whitespace-nowrap !border-0 !p-0 ![clip:rect(0,0,0,0)]">Loading...</span>
                        </div>
                        <p class="mt-2 text-gray-600 dark:text-gray-300">Carregando turnos...</p>
                    </div>

                    <div v-if="!isLoading" class="mt-6 overflow-x-auto">
                        <table class="min-w-full border-collapse">
                            <thead>
                            <tr>
                                <th class="p-2 border bg-gray-50 dark:bg-gray-700 dark:text-white text-center">Turno
                                </th>
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
                                    class="p-2 border text-center align-top"
                                    :class="{
                                    'cursor-pointer': $page.props.auth.user,
                                    'bg-blue-50 dark:bg-blue-900/20': isToday(day),
                                    'bg-blue-500 text-white hover:bg-blue-600': isUserInShift(day, shift),
                                    'hover:bg-gray-100 dark:hover:bg-gray-700': !isUserInShift(day, shift) && $page.props.auth.user
                                }"
                                    @click="$page.props.auth.user && toggleShift(day, shift)"
                                >
                                    <div v-if="weekShifts[formatDateForInput(day)]">
                                        <div
                                            v-for="(user, userIndex) in weekShifts[formatDateForInput(day)][shift.label]"
                                            :key="userIndex"
                                            class="text-sm py-1"
                                        >
                                            {{ typeof user === 'object' ? user.name : user }}
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="$page.props.auth.user" class="mt-6 flex justify-center">
                        <button
                            @click="saveChanges"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="!hasPendingChanges || isSaving"
                        >
                            <span v-if="isSaving">
                                <span
                                    class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-solid border-current border-r-transparent align-[-0.125em] motion-reduce:animate-[spin_1.5s_linear_infinite] mr-2"></span>
                                Salvando...
                            </span>
                            <span v-else>
                                Salvar Alterações
                                <span v-if="hasPendingChanges"
                                      class="ml-2 bg-white text-blue-600 rounded-full px-2 py-1 text-xs">
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

        <div v-if="showUserSelector" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-xl max-w-md w-full">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold">Selecionar Usuário</h3>
                    <button @click="showUserSelector = false" class="text-gray-500 hover:text-gray-700">
                        <span class="text-xl">&times;</span>
                    </button>
                </div>

                <div class="text-sm mb-4">
                    <p>Data: {{ selectedShiftInfo ? formatDateDisplay(selectedShiftInfo.date) : '' }}</p>
                    <p>Turno: {{ selectedShiftInfo ? selectedShiftInfo.shift : '' }}</p>
                </div>

                <div class="max-h-60 overflow-y-auto">
                    <div v-if="allUsers.length === 0" class="text-center py-4 text-gray-500">
                        Carregando usuários...
                    </div>

                    <div v-else class="space-y-2">
                        <button
                            v-for="user in allUsers"
                            :key="user.id"
                            @click="assignUserToShift(user)"
                            class="w-full text-left px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg flex items-center"
                        >
    <span class="flex-grow">
        <span>{{ user.name }}</span>
        <span class="text-xs text-gray-500 ml-2">(ID: {{ user.id }})</span>
    </span>
                            <span v-if="isUserAssignedToSelectedShift(user)"
                                  class="bg-green-500 text-white text-xs px-2 py-1 rounded-full">
        Atribuído
    </span>
                        </button>
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button @click="showUserSelector = false"
                            class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-white">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
