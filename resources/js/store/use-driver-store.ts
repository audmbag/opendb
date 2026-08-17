
import type { UseHttpProps } from "node_modules/@inertiajs/react/types/useHttp";
import { toast } from "sonner";
import { create } from "zustand"
import type { Driver } from "@/types/databases"
import type { APIData } from "@/types/global";
import DriverConfigController from "@/wayfinder/actions/App/Http/Controllers/API/DriverConfigController"


interface DatabaseMSState {
    data: Driver[];
    loadData: (
        httpClient: UseHttpProps<object, APIData<Driver>>['get'],
        callback: (data: Driver[]) => void,
    ) => void;
}

const driverStore = create<DatabaseMSState>((set, get) => ({
    data: [],
    loadData: async (GET, callback) => {

        const data = get().data

        if(data.length > 0) {
            callback(data)

            return
        }

        await GET(DriverConfigController.index.url(), {
            onSuccess: (response: any) => {
                set({ data: response.data });
                callback(response.data);
            },
            onError: () => {
                callback([]);
                toast.error('Impossible de charger les SGBD', {
                    id: 'load-database-ms-error',
                });
            },
        });
    }
}))



export { driverStore }
