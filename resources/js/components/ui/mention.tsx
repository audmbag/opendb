import * as MentionPrimitive from "@diceui/mention";
import * as React from "react";

import { cn } from "@/lib/utils";

const TEXT_SELECTION_HIGHLIGHT = "mention-text-selection";
const TAG_SELECTION_HIGHLIGHT = "mention-tag-selection";

/**
 * Le texte de l'input est transparent : seul le calque de surlignage affiché dessous est visible. La sélection native
 * de l'input ne peut pas colorer une mention à part, elle est donc redessinée sur ce calque avec la CSS Custom Highlight
 * API (voir app.css) : couleurs du navigateur pour le texte, contraste dédié pour les mentions.
 */
function useSelectionHighlight(rootRef: React.RefObject<HTMLDivElement | null>) {
  React.useEffect(() => {
    const root = rootRef.current;

    if (!root || typeof CSS === "undefined" || !("highlights" in CSS)) {
      return;
    }

    const textHighlight = new Highlight();
    const tagHighlight = new Highlight();

    CSS.highlights.set(TEXT_SELECTION_HIGHLIGHT, textHighlight);
    CSS.highlights.set(TAG_SELECTION_HIGHLIGHT, tagHighlight);
    root.setAttribute("data-selection-highlight", "");

    const paint = () => {
      textHighlight.clear();
      tagHighlight.clear();

      const input = root.querySelector<HTMLInputElement | HTMLTextAreaElement>("[data-slot=mention-input]");
      const layer = input?.previousElementSibling;

      if (!input || !layer || document.activeElement !== input) {
        return;
      }

      const { selectionStart: start, selectionEnd: end } = input;

      if (start === null || end === null || start === end) {
        return;
      }

      // Le calque reprend la valeur de l'input en segments successifs : les mentions portent l'attribut data-tag.
      let offset = 0;

      for (const segment of Array.from(layer.children)) {
        const text = segment.firstChild;
        const length = text?.textContent?.length ?? 0;
        const from = Math.max(start, offset);
        const to = Math.min(end, offset + length);

        if (text && from < to) {
          const range = new Range();
          range.setStart(text, from - offset);
          range.setEnd(text, to - offset);

          (segment.hasAttribute("data-tag") ? tagHighlight : textHighlight).add(range);
        }

        offset += length;
      }
    };

    // Le calque est réécrit à chaque saisie : la sélection est repeinte sur ses nouveaux nœuds.
    const observer = new MutationObserver(paint);
    observer.observe(root, { childList: true, subtree: true, characterData: true });

    document.addEventListener("selectionchange", paint);
    root.addEventListener("selectionchange", paint);
    root.addEventListener("focusin", paint);
    root.addEventListener("focusout", paint);

    return () => {
      observer.disconnect();
      document.removeEventListener("selectionchange", paint);
      root.removeEventListener("selectionchange", paint);
      root.removeEventListener("focusin", paint);
      root.removeEventListener("focusout", paint);
      root.removeAttribute("data-selection-highlight");
      CSS.highlights.delete(TEXT_SELECTION_HIGHLIGHT);
      CSS.highlights.delete(TAG_SELECTION_HIGHLIGHT);
    };
  }, [rootRef]);
}

function Mention({
  className,
  ref,
  ...props
}: React.ComponentProps<typeof MentionPrimitive.Root>) {
  const rootRef = React.useRef<HTMLDivElement | null>(null);

  useSelectionHighlight(rootRef);

  const composedRef = React.useCallback(
    (node: HTMLDivElement | null) => {
      rootRef.current = node;

      if (typeof ref === "function") {
        ref(node);
      } else if (ref) {
        ref.current = node;
      }
    },
    [ref],
  );

  return (
    <MentionPrimitive.Root
      ref={composedRef}
      data-slot="mention"
      className={cn(
        // Le texte de l'input est masqué au profit du calque de surlignage, qui seul peut colorer les mentions.
        "**:data-[slot=mention-input]:text-transparent **:data-[slot=mention-input]:caret-foreground [&_div:has(+[data-slot=mention-input])]:text-foreground! **:data-tag:text-blue-400",
        className,
      )}
      {...props}
    />
  );
}

function MentionLabel({
  className,
  ...props
}: React.ComponentProps<typeof MentionPrimitive.Label>) {
  return (
    <MentionPrimitive.Label
      data-slot="mention-label"
      className={cn("px-0.5 py-1.5 font-semibold text-sm", className)}
      {...props}
    />
  );
}

function MentionInput({
  className,
  ...props
}: React.ComponentProps<typeof MentionPrimitive.Input>) {
  return (
    <MentionPrimitive.Input
      data-slot="mention-input"
      className={cn(
        "flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50",
        className,
      )}
      {...props}
    />
  );
}

function MentionContent({
  className,
  children,
  ...props
}: React.ComponentProps<typeof MentionPrimitive.Content>) {
  return (
    <MentionPrimitive.Portal>
      <MentionPrimitive.Content
        data-slot="mention-content"
        className={cn(
          "data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 relative z-50 min-w-32 overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md data-[state=closed]:animate-out data-[state=open]:animate-in",
          className,
        )}
        {...props}
      >
        {children}
      </MentionPrimitive.Content>
    </MentionPrimitive.Portal>
  );
}

function MentionItem({
  className,
  children,
  ...props
}: React.ComponentProps<typeof MentionPrimitive.Item>) {
  return (
    <MentionPrimitive.Item
      data-slot="mention-item"
      className={cn(
        "relative flex w-full cursor-default select-none items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden data-disabled:pointer-events-none data-highlighted:bg-accent data-highlighted:text-accent-foreground data-disabled:opacity-50",
        className,
      )}
      {...props}
    >
      {children}
    </MentionPrimitive.Item>
  );
}

export { Mention, MentionContent, MentionInput, MentionItem, MentionLabel };
