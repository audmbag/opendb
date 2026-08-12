import type {ToasterProps} from "sonner";
import { Toaster as UiToast } from "@/components/ui/sonner"
import { useAppearance } from "@/hooks/use-apparence";

export default function Toaster (props : ToasterProps) {

    const { appearance } = useAppearance()

    return (
        <UiToast {...props} richColors theme={appearance} />
    )
}
