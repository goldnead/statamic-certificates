<script setup>
import { ref } from 'vue';
import { Head, router } from '@statamic/cms/inertia';
import { dateFormatter } from '@statamic/cms/api';
import {
    Badge,
    ConfirmationModal,
    Description,
    DocsCallout,
    DropdownItem,
    EmptyStateItem,
    EmptyStateMenu,
    Field,
    Header,
    Icon,
    Listing,
    Textarea,
} from '@statamic/cms/ui';

const props = defineProps({
    locale: { type: String, default: null },
    setupRequired: { type: Boolean, default: false },
    rows: { type: Array, required: true },
    initialColumns: { type: Array, required: true },
    truncated: { type: Boolean, default: false },
    total: { type: Number, default: 0 },
});

const docsUrl = 'https://docs.adriangoldner.dev/certificates/';

const revoking = ref(null);
const reason = ref('');
const reasonError = ref(null);
const busy = ref(false);

function reload() {
    router.reload();
}

function startRevoke(row) {
    revoking.value = row;
    reason.value = '';
    reasonError.value = null;
}

function closeRevoke(open) {
    if (!open && !busy.value) revoking.value = null;
}

function confirmRevoke() {
    if (!revoking.value) return;

    busy.value = true;

    router.post(revoking.value.revoke_url, { reason: reason.value }, {
        preserveScroll: true,
        onSuccess: () => {
            revoking.value = null;
        },
        onError: (errors) => {
            reasonError.value = errors.reason ?? null;
        },
        onFinish: () => {
            busy.value = false;
        },
    });
}

// Core's formatter in the CP user's language, not the browser's.
function formatDate(value) {
    if (!value) return null;

    return props.locale
        ? dateFormatter.withLocale(props.locale, () => dateFormatter.format(value, 'date'))
        : dateFormatter.format(value, 'date');
}
</script>

<template>
    <Head :title="__('certificates::cp.title')" />

    <div class="max-w-page mx-auto">
        <template v-if="setupRequired || rows.length === 0">
            <!-- Core's empty state is a centred h1 rather than <Header>; see pages/forms/Index.vue. -->
            <header class="py-8 pt-16 text-center">
                <h1 class="text-[25px] font-medium antialiased flex justify-center items-center gap-2 sm:gap-3">
                    <Icon name="document-certificate" class="size-5 text-gray-500" />{{ __('certificates::cp.title') }}
                </h1>
            </header>

            <EmptyStateMenu v-if="setupRequired" :heading="__('certificates::cp.setup_heading')">
                <EmptyStateItem
                    icon="document-certificate"
                    :heading="__('certificates::cp.setup_migrate_heading')"
                    :description="__('certificates::cp.setup_migrate_description')"
                    :href="docsUrl"
                    target="_blank"
                />
            </EmptyStateMenu>

            <EmptyStateMenu v-else :heading="__('certificates::cp.empty_heading')">
                <EmptyStateItem
                    icon="document-certificate"
                    :heading="__('certificates::cp.title')"
                    :description="__('certificates::cp.empty_description')"
                    :href="docsUrl"
                    target="_blank"
                />
            </EmptyStateMenu>
        </template>

        <template v-else>
            <Header :title="__('certificates::cp.title')" icon="document-certificate" />

            <Description
                v-if="truncated"
                class="mb-3"
                :text="__('certificates::cp.truncated', { limit: rows.length, total })"
            />

            <!-- Client mode: search and sort in the browser. No action-url: revoking needs a reason, which core's bulk actions cannot ask for per row. -->
            <Listing
                :items="rows"
                :columns="initialColumns"
                preferences-prefix="certificates.index"
                sort-column="issued_at"
                sort-direction="desc"
                @refreshing="reload"
            >
                <template #cell-issued_at="{ row }">
                    <span class="whitespace-nowrap">{{ formatDate(row.issued_at) }}</span>
                </template>

                <template #cell-status="{ row }">
                    <Badge
                        v-if="row.status === 'revoked'"
                        pill
                        color="red"
                        :text="__('certificates::cp.status_revoked')"
                        v-tooltip="row.revoked_reason"
                    />
                    <Badge v-else pill color="green" :text="__('certificates::cp.status_valid')" />
                </template>

                <template #cell-code="{ row }">
                    <span class="font-mono text-xs">{{ row.code }}</span>
                </template>

                <template #prepended-row-actions="{ row }">
                    <DropdownItem
                        v-if="row.verify_url"
                        :text="__('certificates::cp.verify')"
                        icon="external-link"
                        :href="row.verify_url"
                        target="_blank"
                    />
                    <DropdownItem
                        v-if="row.revoke_url"
                        :text="__('certificates::cp.revoke')"
                        icon="trash"
                        variant="destructive"
                        @click="startRevoke(row)"
                    />
                </template>
            </Listing>
        </template>

        <ConfirmationModal
            :open="revoking !== null"
            :title="__('certificates::cp.revoke_title')"
            :button-text="__('certificates::cp.revoke_confirm')"
            :cancel-text="__('certificates::cp.cancel')"
            :busy="busy"
            :disabled="reason.trim() === ''"
            danger
            @update:open="closeRevoke"
            @cancel="closeRevoke(false)"
            @confirm="confirmRevoke"
        >
            <div class="space-y-4">
                <Description :text="__('certificates::cp.revoke_description')" />
                <Description v-if="revoking" :text="`${revoking.learner_name} · ${revoking.course_title}`" />
                <Field
                    :label="__('certificates::cp.revoke_reason')"
                    :instructions="__('certificates::cp.revoke_reason_help')"
                    :error="reasonError"
                    required
                >
                    <Textarea v-model="reason" :rows="3" />
                </Field>
            </div>
        </ConfirmationModal>

        <DocsCallout :topic="__('certificates::cp.title')" :url="docsUrl" />
    </div>
</template>
