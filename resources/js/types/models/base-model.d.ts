

export interface BaseModel<T = string> {
    id: T,
    created_at?: string,
    updated_at?: string,
}
