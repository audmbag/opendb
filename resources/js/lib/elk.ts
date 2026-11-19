import ElkApi from 'elkjs/lib/elk-api';
import type { ELK } from 'elkjs/lib/elk-api';
import elkWorkerUrl from 'elkjs/lib/elk-worker.min.js?url';

let elk: ELK | null = null;

/**
 * The engine placing the steps of the query plans and the tables of the schemas. About 1.5 MB, it runs in a worker
 * started the first time a graph is drawn, then kept for the next ones: the page stays responsive meanwhile.
 */
export function layoutEngine(): ELK {
    elk ??= new ElkApi({ workerUrl: elkWorkerUrl, workerFactory: startLayoutWorker });

    return elk;
}

/**
 * A worker must come from the origin of the page, which the server of Vite is not in development: a script of the
 * page itself loads the engine, as importScripts may do from any origin.
 */
function startLayoutWorker(url?: string): Worker {
    const script = `importScripts(${JSON.stringify(new URL(url ?? elkWorkerUrl, window.location.href).href)});`;

    return new Worker(URL.createObjectURL(new Blob([script], { type: 'text/javascript' })));
}
