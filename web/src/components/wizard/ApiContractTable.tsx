import { Badge } from "@/components/ui";

export interface ApiContractItem {
  method: string;
  path: string;
  description: string;
  auth: boolean;
}

export function ApiContractTable({ items }: { items: ApiContractItem[] }) {
  return (
    <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
      <table className="w-full text-sm">
        <thead>
          <tr className="bg-[var(--color-surface-2)]">
            <th className="px-3 py-2 text-left font-medium">Method</th>
            <th className="px-3 py-2 text-left font-medium">Endpoint</th>
            <th className="px-3 py-2 text-left font-medium">Deskripsi</th>
            <th className="px-3 py-2 text-left font-medium">Auth</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-[var(--color-border)]">
          {items.map((api, i) => (
            <tr key={i}>
              <td className="px-3 py-2">
                <Badge tone={
                  api.method === 'GET' ? 'success' :
                  api.method === 'POST' ? 'brand' :
                  api.method === 'PUT' || api.method === 'PATCH' ? 'warning' : 'danger'
                }>{api.method}</Badge>
              </td>
              <td className="px-3 py-2 font-mono text-xs">{api.path}</td>
              <td className="px-3 py-2 text-[var(--color-fg-muted)]">{api.description}</td>
              <td className="px-3 py-2">{api.auth ? '✅ Ya' : '❌ Tidak'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
