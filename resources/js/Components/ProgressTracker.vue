<script setup lang="ts">
import { computed } from 'vue'

type StepStatus = 'done' | 'active' | 'waiting'

interface Step {
    name: string
    description: string
    status: StepStatus
}

const props = defineProps<{
    steps: Step[]
}>()

// Status colors for icons
const getIconBgClass = (status: StepStatus) => {
    switch (status) {
        case 'done':
            return 'bg-teal-600'
        case 'active':
            return 'bg-blue-600 animate-pulse'
        case 'waiting':
        default:
            return 'bg-gray-300'
    }
}

const getIconColor = (status: StepStatus) => {
    switch (status) {
        case 'done':
        case 'active':
            return 'text-white'
        case 'waiting':
        default:
            return 'text-gray-500'
    }
}

// Get icon based on status
const getIcon = (status: StepStatus) => {
    switch (status) {
        case 'done':
            return '✓'
        case 'active':
            return '⏳'
        case 'waiting':
        default:
            return '○'
    }
}

// Progress bar width for active step
const activeStepIndex = computed(() => {
    return props.steps.findIndex((step) => step.status === 'active')
})

const progressWidth = computed(() => {
    if (activeStepIndex.value === -1) {
        return props.steps.every((s) => s.status === 'done') ? '100%' : '0%'
    }

    const total = props.steps.length - 1
    const percentage = (activeStepIndex.value / total) * 100
    return `${Math.min(percentage, 100)}%`
})
</script>

<template>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">
            Progress Processing
        </h3>

        <!-- Overall Progress Bar -->
        <div class="mb-8">
            <div class="flex justify-between text-sm text-gray-600 mb-2">
                <span>Status</span>
                <span>{{ steps.filter((s) => s.status === 'done').length }} / {{ steps.length }} selesai</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div
                    class="bg-teal-600 h-2 rounded-full transition-all duration-500"
                    :style="{ width: progressWidth }"
                />
            </div>
        </div>

        <!-- Steps List -->
        <div class="space-y-4">
            <div
                v-for="(step, index) in steps"
                :key="index"
                class="relative"
            >
                <!-- Step Item -->
                <div class="flex items-start space-x-4">
                    <!-- Icon Circle -->
                    <div
                        class="flex-shrink-0 h-10 w-10 rounded-full flex items-center justify-center font-semibold text-sm transition-all duration-300"
                        :class="[getIconBgClass(step.status), getIconColor(step.status)]"
                    >
                        {{ getIcon(step.status) }}
                    </div>

                    <!-- Step Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <h4
                                class="text-base font-medium"
                                :class="{
                                    'text-gray-900': step.status !== 'waiting',
                                    'text-gray-500': step.status === 'waiting'
                                }"
                            >
                                {{ step.name }}
                            </h4>

                            <!-- Status Badge -->
                            <span
                                v-if="step.status === 'active'"
                                class="px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                            >
                                Sedang Diproses
                            </span>

                            <span
                                v-else-if="step.status === 'done'"
                                class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800"
                            >
                                Selesai
                            </span>

                            <span
                                v-else
                                class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600"
                            >
                                Menunggu
                            </span>
                        </div>

                        <p
                            class="text-sm mt-1"
                            :class="{
                                'text-gray-600': step.status !== 'waiting',
                                'text-gray-400': step.status === 'waiting'
                            }"
                        >
                            {{ step.description }}
                        </p>
                    </div>
                </div>

                <!-- Vertical Line (Connector) -->
                <div
                    v-if="index < steps.length - 1"
                    class="absolute left-5 mt-10 w-0.5 h-8 -ml-px"
                    :class="{
                        'bg-teal-600': step.status === 'done',
                        'bg-gray-300': step.status !== 'done'
                    }"
                />
            </div>
        </div>
    </div>
</template>
