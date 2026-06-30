import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import { defineComponent, h } from 'vue';
import ConfirmDialog from '../ConfirmDialog.vue';

const passthroughStub = (tag: string) =>
    defineComponent({
        props: { open: { type: Boolean, default: undefined }, asChild: Boolean },
        emits: ['update:open'],
        setup(_, { slots }) {
            return () => h(tag, {}, slots.default ? slots.default() : []);
        },
    });

const stubs = {
    Dialog: defineComponent({
        props: ['open'],
        emits: ['update:open'],
        setup(props, { slots }) {
            return () => (props.open ? h('div', { 'data-test': 'dialog' }, slots.default?.()) : null);
        },
    }),
    DialogContent: passthroughStub('div'),
    DialogHeader: passthroughStub('div'),
    DialogTitle: passthroughStub('h2'),
    DialogDescription: passthroughStub('p'),
    DialogFooter: passthroughStub('div'),
    DialogClose: passthroughStub('div'),
    Button: defineComponent({
        props: ['variant'],
        setup(props, { slots, attrs }) {
            return () =>
                h(
                    'button',
                    { 'data-variant': props.variant, ...attrs },
                    slots.default?.(),
                );
        },
    }),
};

describe('ConfirmDialog', () => {
    it('renders nothing when open=false', () => {
        const wrapper = mount(ConfirmDialog, {
            props: { open: false },
            global: { stubs },
        });
        expect(wrapper.find('[data-test="dialog"]').exists()).toBe(false);
    });

    it('renders dialog content with title and description when open=true', () => {
        const wrapper = mount(ConfirmDialog, {
            props: { open: true, title: 'Wirklich löschen?', description: 'Das kann nicht rückgängig gemacht werden.' },
            global: { stubs },
        });
        expect(wrapper.find('[data-test="dialog"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Wirklich löschen?');
        expect(wrapper.text()).toContain('Das kann nicht rückgängig gemacht werden.');
    });

    it('emits confirm and closes when the confirm button is clicked', async () => {
        const wrapper = mount(ConfirmDialog, {
            props: { open: true },
            global: { stubs },
        });
        const buttons = wrapper.findAll('button');
        const confirmButton = buttons[buttons.length - 1];
        await confirmButton.trigger('click');

        expect(wrapper.emitted('confirm')).toHaveLength(1);
        expect(wrapper.emitted('update:open')).toEqual([[false]]);
    });

    it('renders destructive variant when destructive=true', () => {
        const wrapper = mount(ConfirmDialog, {
            props: { open: true, destructive: true },
            global: { stubs },
        });
        const confirmButton = wrapper.findAll('button').at(-1);
        expect(confirmButton?.attributes('data-variant')).toBe('destructive');
    });
});
