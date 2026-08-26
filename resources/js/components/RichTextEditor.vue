<script setup lang="ts">
import { Bold, Heading2, Heading3, Italic, List, ListOrdered } from '@lucide/vue';
import Placeholder from '@tiptap/extension-placeholder';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { onBeforeUnmount, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        placeholder?: string;
        class?: string;
    }>(),
    {
        modelValue: '',
        placeholder: '',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            code: false,
            codeBlock: false,
            blockquote: false,
            horizontalRule: false,
        }),
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm dark:prose-invert max-w-none min-h-24 px-3 py-2 outline-none [&_p]:my-1 [&_h2]:mt-2 [&_h2]:mb-1 [&_h3]:mt-2 [&_h3]:mb-1 [&_p.is-editor-empty:first-child::before]:pointer-events-none [&_p.is-editor-empty:first-child::before]:float-left [&_p.is-editor-empty:first-child::before]:h-0 [&_p.is-editor-empty:first-child::before]:text-muted-foreground [&_p.is-editor-empty:first-child::before]:content-[attr(data-placeholder)]',
        },
    },
    onUpdate: ({ editor: instance }) => {
        emit('update:modelValue', instance.getHTML());
    },
});

// The parent resets modelValue (e.g. switching which memory is being
// edited) without remounting this component, so the editor's own content
// needs to be resynced explicitly - otherwise it keeps showing whatever
// was last typed.
watch(
    () => props.modelValue,
    (value) => {
        if (editor.value && value !== editor.value.getHTML()) {
            editor.value.commands.setContent(value, { emitUpdate: false });
        }
    },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});
</script>

<template>
    <div
        :class="
            cn(
                'rounded-md border border-input bg-transparent shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
                props.class,
            )
        "
    >
        <div class="flex items-center gap-0.5 border-b border-input px-1.5 py-1">
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                title="Grassetto"
                :class="{ 'bg-muted': editor?.isActive('bold') }"
                @click="editor?.chain().focus().toggleBold().run()"
            >
                <Bold class="size-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                title="Corsivo"
                :class="{ 'bg-muted': editor?.isActive('italic') }"
                @click="editor?.chain().focus().toggleItalic().run()"
            >
                <Italic class="size-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                title="Titolo"
                :class="{ 'bg-muted': editor?.isActive('heading', { level: 2 }) }"
                @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
            >
                <Heading2 class="size-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                title="Sottotitolo"
                :class="{ 'bg-muted': editor?.isActive('heading', { level: 3 }) }"
                @click="editor?.chain().focus().toggleHeading({ level: 3 }).run()"
            >
                <Heading3 class="size-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                title="Elenco puntato"
                :class="{ 'bg-muted': editor?.isActive('bulletList') }"
                @click="editor?.chain().focus().toggleBulletList().run()"
            >
                <List class="size-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                title="Elenco numerato"
                :class="{ 'bg-muted': editor?.isActive('orderedList') }"
                @click="editor?.chain().focus().toggleOrderedList().run()"
            >
                <ListOrdered class="size-3.5" />
            </Button>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>
