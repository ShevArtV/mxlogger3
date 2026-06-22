<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, nextTick } from 'vue';
// Все компоненты PrimeVue — именованными импортами из единого бандла VueTools.
import {
    DataTable, Column, Tag, Button, SplitButton, InputText, Select, MultiSelect, DatePicker,
    Dialog, Tabs, TabList, Tab, TabPanels, TabPanel, Toast, ConfirmPopup,
    useToast, useConfirm,
} from 'primevue';
import { LogApi } from '../api/connector.js';

const toast = useToast();
const confirm = useConfirm();

const rows = ref([]);
const total = ref(0);
const loading = ref(false);
const first = ref(0);
const limit = ref(50);
const sortField = ref('createdon');
const sortOrder = ref(-1); // -1 DESC, 1 ASC

const levels = [
    { label: 'Все уровни', value: '' },
    { label: 'debug', value: 'debug' },
    { label: 'info', value: 'info' },
    { label: 'warning', value: 'warning' },
    { label: 'error', value: 'error' },
];

const filters = reactive({
    tags: [], // массив выбранных тэгов (multi-select с чекбоксами)
    tags_match: 'all', // как на двойке: выбрано несколько → AND
    level: '',
    process_uid: '',
    ident: '',
    query: '',
    dates: null, // [from, to]
});

const tagOptions = ref([]);
const detail = reactive({ open: false, loading: false, data: null });

async function loadTags() {
    const res = await LogApi.getTags('');
    tagOptions.value = (res && res.results) ? res.results : [];
}

const severity = (level) => ({ error: 'danger', warning: 'warn', info: 'info', debug: 'secondary' }[level] || 'contrast');

function fmtDate(d) {
    if (!d) return '';
    const p = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
}

function buildParams() {
    const p = {
        start: first.value,
        limit: limit.value,
        sort: sortField.value || 'createdon',
        dir: sortOrder.value === 1 ? 'ASC' : 'DESC',
        tags_match: filters.tags_match,
    };
    if (Array.isArray(filters.tags) && filters.tags.length) p.tags = filters.tags.join(',');
    if (filters.level) p.level = filters.level;
    if (filters.process_uid) p.process_uid = filters.process_uid;
    if (filters.ident) p.ident = filters.ident;
    if (filters.query) p.query = filters.query;
    if (Array.isArray(filters.dates)) {
        if (filters.dates[0]) p.date_from = fmtDate(filters.dates[0]);
        if (filters.dates[1]) p.date_to = fmtDate(filters.dates[1]);
    }
    return p;
}

async function load() {
    loading.value = true;
    const res = await LogApi.getList(buildParams());
    loading.value = false;
    if (res && (res.results || res.object)) {
        rows.value = res.results || res.object || [];
        total.value = res.total || 0;
    } else {
        rows.value = [];
        total.value = 0;
        if (res && res.message) {
            toast.add({ severity: 'error', summary: 'Ошибка', detail: res.message, life: 5000 });
        }
    }
}

function applyFilters() {
    first.value = 0;
    load();
}

// Текстовые поля применяются с задержкой — чтобы не слать запрос на каждый символ.
let applyTimer = null;
function debouncedApply() {
    clearTimeout(applyTimer);
    applyTimer = setTimeout(() => applyFilters(), 450);
}

function resetFilters() {
    filters.tags = [];
    filters.level = '';
    filters.process_uid = '';
    filters.ident = '';
    filters.query = '';
    filters.dates = null;
    applyFilters();
}

function refresh() {
    load();
    loadTags();
}

// Динамическая высота: тело таблицы тянется до нижнего края окна.
const wrapRef = ref(null);
const scrollHeight = ref('420px');
function recomputeHeight() {
    nextTick(() => {
        if (!wrapRef.value) return;
        const dt = wrapRef.value.querySelector('.p-datatable') || wrapRef.value;
        const top = dt.getBoundingClientRect().top;
        // вычитаем шапку таблицы + пагинатор + нижний отступ
        scrollHeight.value = Math.max(180, Math.floor(window.innerHeight - top - 108)) + 'px';
    });
}

function onPage(e) {
    first.value = e.first;
    limit.value = e.rows;
    load();
}

function onSort(e) {
    sortField.value = e.sortField || 'createdon';
    sortOrder.value = e.sortOrder || -1;
    first.value = 0;
    load();
}

async function openDetail(row) {
    detail.open = true;
    detail.loading = true;
    detail.data = null;
    const res = await LogApi.get(row.id);
    detail.loading = false;
    detail.data = (res && res.object) ? res.object : row;
}

function filterByTag(tag) {
    if (!Array.isArray(filters.tags)) filters.tags = [];
    if (!filters.tags.includes(tag)) filters.tags = [...filters.tags, tag];
    applyFilters();
}

function filterByProcess(uid) {
    if (!uid) return;
    filters.process_uid = uid;
    applyFilters();
}

// Клик по значению в окне детали → заполнить соответствующий фильтр (как на двойке).
function applyDetailFilter(type, value) {
    if (value === null || value === undefined || value === '') return;
    detail.open = false;
    if (type === 'tag') {
        filterByTag(String(value));
        return;
    }
    if (type === 'level') filters.level = String(value);
    else if (type === 'process') filters.process_uid = String(value);
    else if (type === 'query') filters.query = String(value);
    else if (type === 'ident') filters.ident = String(value);
    applyFilters();
}

// Те же фильтры, что в гриде, без параметров пагинации/сортировки.
function filterParams() {
    const params = buildParams();
    delete params.start;
    delete params.limit;
    delete params.sort;
    delete params.dir;
    return params;
}

// Экспорт журнала с текущими фильтрами — навигация на export.php (скачивание файла).
const exportItems = [
    { label: 'Markdown (.md)', icon: 'pi pi-file', command: () => exportLog('md') },
    { label: 'Текст (.txt)', icon: 'pi pi-file-edit', command: () => exportLog('txt') },
];
function exportLog(format) {
    window.location.href = LogApi.exportUrl(filterParams(), format);
}

function clearLog(event) {
    const params = buildParams();
    delete params.start;
    delete params.limit;
    delete params.sort;
    delete params.dir;
    const hasFilters = Object.keys(params).some((k) => k !== 'tags_match' && params[k]);
    confirm.require({
        target: event.currentTarget,
        message: hasFilters ? 'Удалить записи по текущим фильтрам?' : 'Очистить ВЕСЬ журнал?',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Отмена',
        acceptLabel: 'Удалить',
        acceptClass: 'p-button-danger',
        accept: async () => {
            const res = await LogApi.clear(params);
            if (res && res.success) {
                toast.add({ severity: 'success', summary: 'Готово', detail: res.message || 'Журнал очищен', life: 4000 });
                first.value = 0;
                load();
                loadTags();
            } else {
                toast.add({ severity: 'error', summary: 'Ошибка', detail: (res && res.message) || 'Не удалось очистить', life: 5000 });
            }
        },
    });
}

onMounted(() => {
    load();
    loadTags();
    recomputeHeight();
    window.addEventListener('resize', recomputeHeight);
});
onBeforeUnmount(() => window.removeEventListener('resize', recomputeHeight));
</script>

<template>
    <div style="padding:12px" ref="wrapRef">
        <Toast />
        <ConfirmPopup />

        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px;width:100%">
            <MultiSelect v-model="filters.tags" :options="tagOptions" optionLabel="tag" optionValue="tag"
                placeholder="Тэги" display="chip" filter filterPlaceholder="Поиск по тэгам"
                :showToggleAll="false" :maxSelectedLabels="4" :selectedItemsLabel="'{0} тэгов'"
                scrollHeight="360px" :virtualScrollerOptions="{ itemSize: 36 }"
                style="flex:1 1 200px;min-width:160px" @change="applyFilters">
                <template #empty>Тэгов пока нет</template>
                <template #emptyfilter>Ничего не найдено</template>
            </MultiSelect>
            <Select v-model="filters.level" :options="levels" optionLabel="label" optionValue="value"
                placeholder="Уровень" style="flex:1 1 130px;min-width:120px" @change="applyFilters" />
            <InputText v-model="filters.process_uid" placeholder="Process UID" style="flex:1 1 150px;min-width:130px"
                @input="debouncedApply" @keyup.enter="applyFilters" />
            <InputText v-model="filters.ident" placeholder="Польз./сессия/IP" style="flex:1 1 150px;min-width:130px"
                @input="debouncedApply" @keyup.enter="applyFilters" />
            <DatePicker v-model="filters.dates" selectionMode="range" showTime hourFormat="24"
                dateFormat="yy-mm-dd" placeholder="Период" style="flex:1 1 210px;min-width:190px" :manualInput="false"
                @update:modelValue="applyFilters" />
            <InputText v-model="filters.query" placeholder="Поиск по тексту/источнику" style="flex:2 1 200px;min-width:180px"
                @input="debouncedApply" @keyup.enter="applyFilters" />
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px">
            <Button label="Показать" icon="pi pi-search" raised @click="applyFilters" />
            <Button label="Сброс" icon="pi pi-times" severity="secondary" raised @click="resetFilters" />
            <span style="flex:1"></span>
            <SplitButton label="Экспорт" icon="pi pi-download" severity="info" raised
                :model="exportItems" @click="exportLog('md')" />
            <Button label="Обновить" icon="pi pi-refresh" severity="secondary" raised @click="refresh" />
            <Button label="Очистить журнал" icon="pi pi-trash" severity="danger" raised @click="clearLog" />
        </div>

        <DataTable :value="rows" :loading="loading" lazy paginator :rows="limit" :first="first"
            :totalRecords="total" :rowsPerPageOptions="[25, 50, 100, 200]" dataKey="id"
            :sortField="sortField" :sortOrder="sortOrder" @page="onPage" @sort="onSort"
            @rowDblclick="(e) => openDetail(e.data)" rowHover
            removableSort size="small" stripedRows scrollable :scrollHeight="scrollHeight"
            paginatorTemplate="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink RowsPerPageDropdown"
            currentPageReportTemplate="{first}–{last} из {totalRecords}">
            <template #empty>
                <div style="padding:16px;color:#888">Записей нет. Измените фильтры или дождитесь логов.</div>
            </template>

            <Column field="createdon" header="Время" sortable style="width:150px">
                <template #body="{ data }"><tt>{{ data.createdon_formatted }}</tt></template>
            </Column>
            <Column field="level" header="Ур." sortable style="width:90px">
                <template #body="{ data }">
                    <Tag :value="data.level" :severity="severity(data.level)" />
                </template>
            </Column>
            <Column header="Тэги" style="width:160px">
                <template #body="{ data }">
                    <Tag v-for="t in data.tags_list" :key="t" :value="t" severity="secondary" style="margin:1px" />
                </template>
            </Column>
            <Column field="process_uid" header="Процесс" style="width:120px">
                <template #body="{ data }">
                    <span v-if="data.process_uid" style="font-family:monospace;font-size:11px">{{ data.process_uid }}</span>
                </template>
            </Column>
            <Column field="message" header="Сообщение" style="width:260px">
                <template #body="{ data }">{{ data.message_short }}</template>
            </Column>
            <Column header="Источник" style="min-width:340px">
                <template #body="{ data }">
                    <span style="font-family:monospace;font-size:11px">{{ data.caller }}</span><br>
                    <span style="color:#999;font-size:11px">{{ data.source }}</span>
                </template>
            </Column>
            <Column header="Польз./IP" style="width:130px">
                <template #body="{ data }">
                    <span>{{ data.username || data.user_id }}</span><br>
                    <span style="color:#999;font-size:11px">{{ data.ip }}</span>
                </template>
            </Column>
            <Column style="width:60px">
                <template #body="{ data }">
                    <Button icon="pi pi-eye" text rounded size="small" @click="openDetail(data)" />
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="detail.open" modal header="Запись лога" :style="{ width: '720px' }"
            :breakpoints="{ '960px': '90vw' }">
            <div v-if="detail.loading" style="padding:24px;text-align:center">Загрузка…</div>
            <div v-else-if="detail.data">
                <p style="font-weight:600;font-size:15px;margin:0 0 12px">{{ detail.data.message }}</p>

                <table class="mxl-detail">
                    <tbody>
                        <tr>
                            <th>Время</th>
                            <td><tt>{{ detail.data.createdon_formatted }}</tt></td>
                        </tr>
                        <tr>
                            <th>Уровень</th>
                            <td>
                                <Tag :value="detail.data.level" :severity="severity(detail.data.level)"
                                    style="cursor:pointer" @click="applyDetailFilter('level', detail.data.level)" />
                            </td>
                        </tr>
                        <tr v-if="detail.data.tags_list && detail.data.tags_list.length">
                            <th>Тэги</th>
                            <td>
                                <Tag v-for="t in detail.data.tags_list" :key="t" :value="t" severity="secondary"
                                    style="margin:1px;cursor:pointer" @click="applyDetailFilter('tag', t)" />
                            </td>
                        </tr>
                        <tr v-if="detail.data.process_uid">
                            <th>Процесс</th>
                            <td><a class="mxl-fval" @click="applyDetailFilter('process', detail.data.process_uid)">{{ detail.data.process_uid }}</a></td>
                        </tr>
                        <tr v-if="detail.data.caller">
                            <th>Источник</th>
                            <td><a class="mxl-fval" @click="applyDetailFilter('query', detail.data.caller)">{{ detail.data.caller }}</a></td>
                        </tr>
                        <tr v-if="detail.data.source">
                            <th>Файл:строка</th>
                            <td><a class="mxl-fval" @click="applyDetailFilter('query', detail.data.source)">{{ detail.data.source }}</a></td>
                        </tr>
                        <tr>
                            <th>Пользователь</th>
                            <td>
                                <a class="mxl-fval" @click="applyDetailFilter('ident', detail.data.username || detail.data.user_id)">
                                    {{ detail.data.username || '—' }}<template v-if="detail.data.user_id"> (#{{ detail.data.user_id }})</template>
                                </a>
                            </td>
                        </tr>
                        <tr v-if="detail.data.session_id">
                            <th>Сессия</th>
                            <td><a class="mxl-fval" @click="applyDetailFilter('ident', detail.data.session_id)">{{ detail.data.session_id }}</a></td>
                        </tr>
                        <tr v-if="detail.data.ip">
                            <th>IP</th>
                            <td><a class="mxl-fval" @click="applyDetailFilter('ident', detail.data.ip)">{{ detail.data.ip }}</a></td>
                        </tr>
                    </tbody>
                </table>

                <Tabs v-if="detail.data.context_pretty || detail.data.trace_pretty" value="context">
                    <TabList>
                        <Tab value="context">Контекст</Tab>
                        <Tab value="trace">Стэк и параметры</Tab>
                    </TabList>
                    <TabPanels>
                        <TabPanel value="context">
                            <pre v-if="detail.data.context_pretty" class="mxl-pre">{{ detail.data.context_pretty }}</pre>
                            <div v-else style="color:#999;padding:8px">Нет данных</div>
                        </TabPanel>
                        <TabPanel value="trace">
                            <pre v-if="detail.data.trace_pretty" class="mxl-pre">{{ detail.data.trace_pretty }}</pre>
                            <div v-else style="color:#999;padding:8px">Нет данных</div>
                        </TabPanel>
                    </TabPanels>
                </Tabs>
            </div>
        </Dialog>
    </div>
</template>

<style>
.mxl-detail { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 12px; }
.mxl-detail th { text-align: left; vertical-align: top; padding: 5px 10px 5px 0; color: #6b7280; font-weight: 600; white-space: nowrap; width: 130px; }
.mxl-detail td { padding: 5px 0; vertical-align: top; word-break: break-word; }
.mxl-detail tr + tr th, .mxl-detail tr + tr td { border-top: 1px solid #eef1f4; }
.mxl-fval { cursor: pointer; color: #2563eb; }
.mxl-fval:hover { text-decoration: underline; }
.mxl-pre { background: #1e2329; color: #d6dee6; padding: 8px; border-radius: 4px; max-height: 320px; overflow: auto; white-space: pre-wrap; word-break: break-word; font-size: 12px; margin: 8px 0 0; }
</style>
