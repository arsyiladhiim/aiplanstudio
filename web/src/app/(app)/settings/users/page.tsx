"use client";
import { useEffect, useRef, useState } from "react";
import { Card, Badge, Input, Label, Modal } from "@/components/ui";
import { Button } from "@/components/ui/Button";
import {
  apiGet,
  apiPost,
  apiPatch,
  apiDelete,
  ApiError,
  type User as UserType,
} from "@/lib/api";
import {
  UserPlus,
  Trash2,
  Shield,
  Lock,
  Loader2,
  CheckCircle2,
  AlertCircle,
  Check,
  Ban,
  ShieldAlert,
} from "lucide-react";

export default function UsersSettings() {
  const [users, setUsers] = useState<UserType[]>([]);
  const [loading, setLoading] = useState(true);
  const [denied, setDenied] = useState(false);
  const [updating, setUpdating] = useState<number | null>(null);
  const [deleting, setDeleting] = useState<number | null>(null);
  const [showModal, setShowModal] = useState(false);
  const [formName, setFormName] = useState("");
  const [formEmail, setFormEmail] = useState("");
  const [formPassword, setFormPassword] = useState("");
  const [formRole, setFormRole] = useState<"admin" | "member">("member");
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState("");
  const [submitSuccess, setSubmitSuccess] = useState(false);
  const [actionError, setActionError] = useState("");
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [bulkBusy, setBulkBusy] = useState(false);
  const previousPendingRef = useRef(0);

  const activeAdminCount = users.filter(
    (u) => u.role === "admin" && u.status === "active",
  ).length;

  function loadUsers() {
    return apiGet<UserType[]>("/settings/users")
      .then((fresh) => {
        const newPending = fresh.filter((u) => u.status === "pending").length;
        if (
          previousPendingRef.current > 0 &&
          newPending > previousPendingRef.current
        ) {
          const added = newPending - previousPendingRef.current;
          setActionError(
            `🔔 ${added} permintaan persetujuan baru masuk. Segera tinjau di daftar.`,
          );
        }
        previousPendingRef.current = newPending;
        setUsers(fresh);
      })
      .catch((e: unknown) => {
        if (e instanceof ApiError && e.status === 403) setDenied(true);
      });
  }

  useEffect(() => {
    loadUsers().finally(() => setLoading(false));
    const interval = setInterval(loadUsers, 30_000);
    return () => clearInterval(interval);
  }, []);

  async function approve(id: number) {
    setUpdating(id);
    setActionError("");
    try {
      const updated = await apiPatch<UserType>(`/settings/users/${id}`, {
        status: "active",
      });
      setUsers((u) => u.map((x) => (x.id === id ? updated : x)));
    } catch (err: unknown) {
      setActionError(
        err instanceof Error ? err.message : "Gagal menyetujui pengguna.",
      );
    } finally {
      setUpdating(null);
    }
  }

  async function remove(id: number) {
    setDeleting(id);
    setActionError("");
    try {
      await apiDelete("/settings/users/" + id);
      setUsers((u) => u.filter((x) => x.id !== id));
    } catch (err: unknown) {
      setActionError(
        err instanceof Error ? err.message : "Gagal menghapus pengguna.",
      );
    } finally {
      setDeleting(null);
    }
  }

  async function handleAdd(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setSubmitError("");
    setSubmitSuccess(false);
    try {
      const newUser = await apiPost<UserType>("/settings/users", {
        name: formName,
        email: formEmail,
        password: formPassword,
        role: formRole,
      });
      setUsers((u) => [...u, newUser]);
      setSubmitSuccess(true);
      setFormName("");
      setFormEmail("");
      setFormPassword("");
      setFormRole("member");
      setTimeout(() => {
        setShowModal(false);
        setSubmitSuccess(false);
      }, 1000);
    } catch (err: unknown) {
      setSubmitError(
        err instanceof Error ? err.message : "Gagal menambah user",
      );
    } finally {
      setSubmitting(false);
    }
  }

  // CP-18.F2: bulk selection + action.
  const selectableIds = users.filter((u) => u.status === "pending").map((u) => u.id);
  const allSelected = selectableIds.length > 0 && selectableIds.every((id) => selectedIds.has(id));

  function toggleOne(id: number) {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  function toggleAll() {
    if (allSelected) setSelectedIds(new Set());
    else setSelectedIds(new Set(selectableIds));
  }

  async function runBulk(action: "approve" | "reject") {
    if (selectedIds.size === 0) return;
    setBulkBusy(true);
    setActionError("");
    try {
      const res = await apiPost<{ affected: { approved: number; rejected: number; deleted: number }; skipped: Array<{ id: number; reason: string }> }>(
        "/settings/users/bulk-action",
        { action, user_ids: Array.from(selectedIds) },
      );
      const { approved, rejected, deleted } = res.affected;
      const skipped = res.skipped;
      const summary = [
        approved && `${approved} disetujui`,
        rejected && `${rejected} ditolak`,
        deleted && `${deleted} dihapus`,
      ].filter(Boolean).join(", ");
      setActionError(
        (summary || "Selesai") +
          (skipped.length ? ` · ${skipped.length} dilewati` : ""),
      );
      setSelectedIds(new Set());
      await loadUsers();
    } catch (err: unknown) {
      setActionError(
        err instanceof Error ? err.message : `Gagal bulk-${action}`,
      );
    } finally {
      setBulkBusy(false);
    }
  }

  if (loading) {
    return (
      <div className="flex h-40 items-center justify-center">
        <Loader2
          size={24}
          className="animate-spin text-[var(--color-fg-muted)]"
        />
      </div>
    );
  }

  if (denied) {
    return (
      <Card className="flex flex-col items-center gap-3 p-10 text-center">
        <Lock size={32} className="text-[var(--color-danger)]" />
        <h3 className="font-semibold">Akses Ditolak</h3>
        <p className="text-sm text-[var(--color-fg-muted)]">
          Halaman ini hanya untuk administrator.
        </p>
      </Card>
    );
  }

  return (
    <Card className="overflow-hidden p-0">
      <div className="flex items-center justify-between border-b border-[var(--color-border)] p-5">
        <div>
          <h3 className="font-semibold">Pengguna</h3>
          <p className="text-sm text-[var(--color-fg-muted)]">
            {users.length} pengguna terdaftar
            {users.some((u) => u.status === "pending") && (
              <>
                {" · "}
                <span className="text-[var(--color-warning)]">
                  {users.filter((u) => u.status === "pending").length} menunggu
                  persetujuan
                </span>
              </>
            )}
            {" · "}
            <span>
              {activeAdminCount} admin aktif
            </span>
          </p>
        </div>
        <Button
          size="sm"
          data-testid="user-add"
          onClick={() => setShowModal(true)}
        >
          <UserPlus size={15} /> Tambah
        </Button>
      </div>

      {actionError && (
        <div className="flex items-center gap-2 border-b border-[var(--color-danger)]/30 bg-[var(--color-danger)]/10 px-5 py-3 text-sm text-[var(--color-danger)]">
          <AlertCircle size={16} />
          <span>{actionError}</span>
          <button
            type="button"
            onClick={() => setActionError("")}
            className="ml-auto text-xs underline hover:no-underline"
          >
            Tutup
          </button>
        </div>
      )}

      {activeAdminCount === 1 && (
        <div className="flex items-center gap-2 border-b border-[var(--color-warning)]/30 bg-[var(--color-warning)]/10 px-5 py-3 text-sm text-[var(--color-warning)]">
          <ShieldAlert size={16} />
          <span>
            Hanya ada <strong>1 admin aktif</strong>. Tidak bisa demote atau
            hapus admin terakhir — promote admin lain dulu untuk mencegah
            lockout sistem.
          </span>
        </div>
      )}

      {selectableIds.length > 0 && (
        <div className="flex items-center gap-3 border-b border-[var(--color-border)] bg-[var(--color-surface-2)]/40 px-5 py-2 text-sm">
          <label className="flex items-center gap-2 text-[var(--color-fg-muted)]">
            <input
              type="checkbox"
              checked={allSelected}
              onChange={toggleAll}
              data-testid="user-select-all"
              className="h-4 w-4 rounded border-[var(--color-border)]"
            />
            <span>
              {allSelected ? "Batal pilih semua" : "Pilih semua pending"}{" "}
              <span className="text-[var(--color-fg-subtle)]">
                ({selectableIds.length})
              </span>
            </span>
          </label>
          {selectedIds.size > 0 && (
            <>
              <span className="text-[var(--color-fg-subtle)]">·</span>
              <span className="font-medium">{selectedIds.size} dipilih</span>
              <div className="ml-auto flex items-center gap-1.5">
                <Button
                  size="sm"
                  variant="ghost"
                  disabled={bulkBusy}
                  onClick={() => runBulk("approve")}
                  data-testid="user-bulk-approve"
                >
                  {bulkBusy ? <Loader2 size={14} className="animate-spin" /> : <Check size={14} className="text-[var(--color-success)]" />}
                  Setujui
                </Button>
                <Button
                  size="sm"
                  variant="ghost"
                  disabled={bulkBusy}
                  onClick={() => runBulk("reject")}
                  data-testid="user-bulk-reject"
                >
                  {bulkBusy ? <Loader2 size={14} className="animate-spin" /> : <Ban size={14} className="text-[var(--color-danger)]" />}
                  Tolak
                </Button>
              </div>
            </>
          )}
        </div>
      )}

      {users.length === 0 ? (
        <div className="flex flex-col items-center gap-2 p-10 text-center">
          <UserPlus size={28} className="text-[var(--color-fg-subtle)]" />
          <p className="text-sm text-[var(--color-fg-muted)]">
            Belum ada pengguna lain.
          </p>
          <Button
            variant="secondary"
            size="sm"
            onClick={() => setShowModal(true)}
          >
            Tambah Pengguna Pertama
          </Button>
        </div>
      ) : users.every((u) => u.status !== "pending") ? (
        <div className="flex items-center gap-2 px-5 py-3 text-sm text-[var(--color-fg-subtle)]">
          <CheckCircle2 size={15} />
          Tidak ada permintaan persetujuan saat ini.
        </div>
      ) : null}

      <div className="divide-y divide-[var(--color-border)]">
        {users.map((u) => {
          const isPending = u.status === "pending";
          return (
            <div
              key={u.id}
              className="flex items-center gap-4 p-4"
              data-testid={"user-" + u.id}
            >
              {isPending ? (
                <input
                  type="checkbox"
                  checked={selectedIds.has(u.id)}
                  onChange={() => toggleOne(u.id)}
                  data-testid={"user-check-" + u.id}
                  className="h-4 w-4 rounded border-[var(--color-border)]"
                  aria-label={`Pilih ${u.name}`}
                />
              ) : (
                <span className="inline-block h-4 w-4" />
              )}
              <div className="grid h-10 w-10 place-items-center rounded-full bg-[var(--color-surface-2)] text-sm font-semibold">
                {u.name.charAt(0).toUpperCase()}
              </div>
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  <span className="truncate font-medium">{u.name}</span>
                  {u.role === "admin" ? (
                    <Badge tone="brand">
                      <Shield size={11} /> Admin
                    </Badge>
                  ) : (
                    <Badge tone="muted">Member</Badge>
                  )}
                  {isPending && (
                    <Badge tone="warning">
                      <Loader2 size={11} className="animate-spin" /> Menunggu
                      Persetujuan
                    </Badge>
                  )}
                </div>
                <div className="truncate text-sm text-[var(--color-fg-subtle)]">
                  {u.email}
                </div>
              </div>
              {isPending ? (
                <div className="flex items-center gap-1.5">
                  <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Terima"
                    disabled={updating === u.id}
                    onClick={() => approve(u.id)}
                  >
                    {updating === u.id ? (
                      <Loader2 size={16} className="animate-spin" />
                    ) : (
                      <Check
                        size={16}
                        className="text-[var(--color-success)]"
                      />
                    )}
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Tolak"
                    disabled={deleting === u.id}
                    onClick={() => remove(u.id)}
                  >
                    {deleting === u.id ? (
                      <Loader2 size={16} className="animate-spin" />
                    ) : (
                      <Ban size={16} className="text-[var(--color-danger)]" />
                    )}
                  </Button>
                </div>
              ) : (
                <Button
                  variant="ghost"
                  size="icon"
                  aria-label="Hapus"
                  disabled={
                    u.role === "admin" ||
                    deleting === u.id
                  }
                  title={
                    u.role === "admin" && activeAdminCount === 1
                      ? "Tidak bisa hapus admin terakhir"
                      : undefined
                  }
                  onClick={() => remove(u.id)}
                >
                  {deleting === u.id ? (
                    <Loader2 size={16} className="animate-spin" />
                  ) : (
                    <Trash2 size={16} />
                  )}
                </Button>
              )}
            </div>
          );
        })}
      </div>

      <Modal open={showModal} onClose={() => setShowModal(false)} title="Tambah Pengguna" size="sm">
        <form className="space-y-4" onSubmit={handleAdd}>
          <div>
            <Label htmlFor="add-name">Nama</Label>
            <Input
              id="add-name"
              value={formName}
              onChange={(e) => setFormName(e.target.value)}
              placeholder="Nama lengkap"
              required
            />
          </div>
          <div>
            <Label htmlFor="add-email">Email</Label>
            <Input
              id="add-email"
              type="email"
              value={formEmail}
              onChange={(e) => setFormEmail(e.target.value)}
              placeholder="user@email.com"
              required
            />
          </div>
          <div>
            <Label htmlFor="add-password">Password</Label>
            <Input
              id="add-password"
              type="password"
              value={formPassword}
              onChange={(e) => setFormPassword(e.target.value)}
              placeholder="Min. 8 karakter"
              required
              minLength={8}
            />
          </div>
          <div>
            <Label htmlFor="add-role">Role</Label>
            <select
              id="add-role"
              value={formRole}
              onChange={(e) =>
                setFormRole(e.target.value as "admin" | "member")
              }
              className="flex h-10 w-full rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2 text-sm outline-none focus:border-[var(--color-brand)]"
            >
              <option value="member">Member</option>
              <option value="admin">Admin</option>
            </select>
          </div>

          {submitSuccess && (
            <div className="flex items-center gap-2 rounded-lg border border-green-500/40 bg-green-500/10 px-4 py-3 text-sm text-green-500">
              <CheckCircle2 size={16} /> Berhasil ditambahkan
            </div>
          )}
          {submitError && (
            <div className="flex items-center gap-2 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-500">
              <AlertCircle size={16} /> {submitError}
            </div>
          )}

          <div className="flex gap-2 pt-2">
            <Button
              type="submit"
              disabled={submitting}
              className="flex-1"
              data-testid="add-user-submit"
            >
              {submitting ? "Menyimpan…" : "Simpan"}
            </Button>
            <Button
              type="button"
              variant="secondary"
              onClick={() => setShowModal(false)}
            >
              Batal
            </Button>
          </div>
        </form>
      </Modal>
    </Card>
  );
}
