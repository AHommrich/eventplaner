import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h } from 'vue';
import InfoTooltip from '../InfoTooltip.vue';

const stubs = {
    TooltipProvider: defineComponent({
        setup(_, { slots }) {
            return () => h('div', {}, slots.default?.());
        },
    }),
    Tooltip: defineComponent({
        props: { open: { type: Boolean, default: false } },
        emits: ['update:open'],
        setup(props, { slots }) {
            return () => h('div', { 'data-tooltip-open': String(props.open) }, slots.default?.());
        },
    }),
    TooltipTrigger: defineComponent({
        props: ['asChild'],
        setup(_, { slots }) {
            return () => h('div', {}, slots.default?.());
        },
    }),
    TooltipContent: defineComponent({
        setup(_, { slots }) {
            return () => h('div', { 'data-test': 'tooltip-content' }, slots.default?.());
        },
    }),
};

describe('InfoTooltip', () => {
    it('starts closed', () => {
        const wrapper = mount(InfoTooltip, {
            props: { text: 'Hinweis' },
            global: { stubs },
        });
        expect(wrapper.attributes()).not.toHaveProperty('data-tooltip-open');
        expect(wrapper.find('[data-tooltip-open="false"]').exists()).toBe(true);
    });

    it('toggles open on button click (mobile-friendly behavior)', async () => {
        const wrapper = mount(InfoTooltip, {
            props: { text: 'Hinweis' },
            global: { stubs },
        });
        const trigger = wrapper.find('button');
        await trigger.trigger('click');
        expect(wrapper.find('[data-tooltip-open="true"]').exists()).toBe(true);
    });

    it('toggles closed on a second click', async () => {
        const wrapper = mount(InfoTooltip, {
            props: { text: 'Hinweis' },
            global: { stubs },
        });
        const trigger = wrapper.find('button');
        await trigger.trigger('click');
        await trigger.trigger('click');
        expect(wrapper.find('[data-tooltip-open="false"]').exists()).toBe(true);
    });

    it('renders the provided text inside the tooltip content', () => {
        const wrapper = mount(InfoTooltip, {
            props: { text: 'Diese Erklärung sollte sichtbar sein.' },
            global: { stubs },
        });
        expect(wrapper.find('[data-test="tooltip-content"]').text()).toBe('Diese Erklärung sollte sichtbar sein.');
    });
});
