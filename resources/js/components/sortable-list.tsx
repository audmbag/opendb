import type {
    Announcements,
    DragEndEvent,
    Modifier,
    UniqueIdentifier,
} from '@dnd-kit/core';
import {
    closestCenter,
    DndContext,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useReducedMotion } from 'motion/react';
import type { CSSProperties, ReactNode } from 'react';
import { trans } from '@/lib/i18n';

type Props = {
    /**
     * The ids of the items, in their order.
     */
    items: string[];
    /**
     * Called when an item is dropped on the place of another one.
     */
    onMove: (id: string, overId: string) => void;
    /**
     * How the screen readers name an item.
     */
    label: (id: string) => string;
    children: ReactNode;
};

/**
 * The items only go up and down.
 */
const verticalOnly: Modifier = ({ transform }) => ({ ...transform, x: 0 });

/**
 * A list whose items are dragged into another order by their handle, with the mouse, a finger or the keyboard: the
 * other items make way for the one dragged as it goes.
 */
export default function SortableList({
    items,
    onMove,
    label,
    children,
}: Props) {
    const sensors = useSensors(
        // A click on the handle stays a click: the drag starts once the pointer moved a little.
        useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const name = (id: UniqueIdentifier) => label(String(id));
    const announcements: Announcements = {
        onDragStart: ({ active }) =>
            trans('common.sortable.picked_up', { item: name(active.id) }),
        onDragOver: ({ active, over }) =>
            over
                ? trans('common.sortable.moved_over', {
                      item: name(active.id),
                      over: name(over.id),
                  })
                : undefined,
        onDragEnd: ({ active, over }) =>
            over && over.id !== active.id
                ? trans('common.sortable.dropped', {
                      item: name(active.id),
                      over: name(over.id),
                  })
                : trans('common.sortable.dropped_in_place', {
                      item: name(active.id),
                  }),
        onDragCancel: ({ active }) =>
            trans('common.sortable.cancelled', { item: name(active.id) }),
    };

    const drop = ({ active, over }: DragEndEvent) => {
        if (over && over.id !== active.id) {
            onMove(String(active.id), String(over.id));
        }
    };

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            modifiers={[verticalOnly]}
            accessibility={{
                announcements,
                screenReaderInstructions: {
                    draggable: trans('common.sortable.instructions'),
                },
            }}
            onDragEnd={drop}
        >
            <SortableContext
                items={items}
                strategy={verticalListSortingStrategy}
            >
                {children}
            </SortableContext>
        </DndContext>
    );
}

/**
 * An item of a SortableList: its node follows the drag, and its handle starts it. The other items slide out of the
 * way and the item settles into its place, unless the system asks for fewer animations.
 */
export function useSortableItem(id: string, disabled = false) {
    const reducedMotion = useReducedMotion();
    const {
        attributes,
        listeners,
        setNodeRef,
        setActivatorNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({
        id,
        disabled,
        transition: reducedMotion
            ? null
            : { duration: 220, easing: 'cubic-bezier(0.25, 1, 0.5, 1)' },
    });

    const style: CSSProperties = {
        transform: CSS.Translate.toString(transform),
        transition,
        position: 'relative',
        zIndex: isDragging ? 10 : undefined,
    };

    return {
        setNodeRef,
        setHandleRef: setActivatorNodeRef,
        handleProps: { ...attributes, ...listeners },
        style,
        dragging: isDragging,
    };
}
