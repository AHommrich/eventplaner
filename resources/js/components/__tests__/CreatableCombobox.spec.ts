import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import axios from 'axios';
import CreatableCombobox from '../CreatableCombobox.vue';

vi.mock('axios', () => ({
    default: { post: vi.fn() },
}));

const MultiselectStub = defineComponent({
    name: 'Multiselect',
    inheritAttrs: false,
    props: {
        modelValue: { type: null, default: null },
        options: { type: Array, default: () => [] },
        placeholder: String,
        valueProp: String,
        label: String,
        trackBy: String,
        searchable: Boolean,
        canClear: Boolean,
        canDeselect: Boolean,
        closeOnSelect: Boolean,
        noOptionsText: String,
        createOption: Boolean,
        onCreate: { type: Function, default: undefined },
    },
    emits: ['update:modelValue', 'open', 'close', 'search-change'],
    setup(_, { slots, attrs }) {
        return () =>
            h('div', { 'data-test': 'multiselect', ...attrs }, [
                slots.option
                    ? h('div', { 'data-test': 'option-slot' }, [
                          slots.option({
                              option: { label: 'Familie Müller', value: 'new', __CREATE__: true },
                          }),
                      ])
                    : null,
            ]);
    },
});

const baseProps = {
    options: [
        { id: 1, label: 'Familie Müller' },
        { id: 2, label: 'Familie Schmidt' },
    ],
    modelValue: null,
    createRoute: 'groups.store',
    createField: 'name',
};

function mountCombobox(propsOverride: Record<string, unknown> = {}) {
    return mount(CreatableCombobox, {
        props: { ...baseProps, ...propsOverride },
        global: { stubs: { Multiselect: MultiselectStub } },
    });
}

describe('CreatableCombobox', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('passes existing options to Multiselect mapped to {value,label}', () => {
        const wrapper = mountCombobox();
        const ms = wrapper.findComponent(MultiselectStub);
        expect(ms.exists()).toBe(true);
        expect(ms.props('options')).toEqual([
            { value: 1, label: 'Familie Müller' },
            { value: 2, label: 'Familie Schmidt' },
        ]);
    });

    it('stops ESC propagation when the dropdown is open', async () => {
        const wrapper = mountCombobox();
        await wrapper.findComponent(MultiselectStub).vm.$emit('open');
        await nextTick();

        const root = wrapper.find('[data-test="multiselect"]').element as HTMLElement;
        const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true });
        const stopSpy = vi.spyOn(event, 'stopPropagation');
        root.dispatchEvent(event);

        expect(stopSpy).toHaveBeenCalledTimes(1);
    });

    it('does not stop ESC propagation when the dropdown is closed', async () => {
        const wrapper = mountCombobox();
        await wrapper.findComponent(MultiselectStub).vm.$emit('open');
        await wrapper.findComponent(MultiselectStub).vm.$emit('close');
        await nextTick();

        const root = wrapper.find('[data-test="multiselect"]').element as HTMLElement;
        const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true });
        const stopSpy = vi.spyOn(event, 'stopPropagation');
        root.dispatchEvent(event);

        expect(stopSpy).not.toHaveBeenCalled();
    });

    it('creates a new option via API and emits update:modelValue + created', async () => {
        (axios as unknown as { post: ReturnType<typeof vi.fn> }).post = vi.fn().mockResolvedValue({
            data: { id: 99, name: 'Familie Neu' },
        });

        const wrapper = mountCombobox();
        const onCreate = wrapper.findComponent(MultiselectStub).props('onCreate') as (
            opt: { label: string },
        ) => Promise<unknown>;

        await onCreate({ label: 'Familie Neu' });

        expect(axios.post).toHaveBeenCalledTimes(1);
        expect(axios.post).toHaveBeenCalledWith('/_test/groups.store', { name: 'Familie Neu' });
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([99]);
        expect(wrapper.emitted('created')?.[0]).toEqual([{ id: 99, name: 'Familie Neu' }]);
    });

    it('renders the "create again" hint when the typed query matches an existing option', async () => {
        const wrapper = mountCombobox();
        await wrapper.findComponent(MultiselectStub).vm.$emit('search-change', 'Familie Müller');
        await nextTick();

        const slot = wrapper.find('[data-test="option-slot"]');
        expect(slot.exists()).toBe(true);
        expect(slot.text()).toContain('Erneut anlegen');
    });
});
