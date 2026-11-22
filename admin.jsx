const { useEffect, useMemo, useState } = React;

const loanStatuses = {
    menunggu_persetujuan: { label: "Menunggu persetujuan", style: "bg-amber-50 text-amber-700 ring-amber-600/10" },
    disetujui: { label: "Disetujui", style: "bg-blue-50 text-blue-700 ring-blue-600/10" },
    ditolak: { label: "Ditolak", style: "bg-rose-50 text-rose-700 ring-rose-600/10" },
    dipinjam: { label: "Sedang dipinjam", style: "bg-violet-50 text-violet-700 ring-violet-600/10" },
    menunggu_verifikasi_pengembalian: { label: "Verifikasi pengembalian", style: "bg-orange-50 text-orange-700 ring-orange-600/10" },
    selesai: { label: "Selesai", style: "bg-emerald-50 text-emerald-700 ring-emerald-600/10" },
    dibatalkan: { label: "Dibatalkan", style: "bg-slate-100 text-slate-600 ring-slate-500/10" },
};

function AdminIcon({ name, className = "h-5 w-5" }) {
    const shapes = {
        home: <><path d="m3 10 9-7 9 7v9a2 2 0 0 1-2 2h-4v-7H9v7H5a2 2 0 0 1-2-2v-9Z" /></>,
        users: <><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m6-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm6-7.5a4 4 0 0 1 0 7.75M20 21v-2a4 4 0 0 0-3-3.87" /></>,
        box: <><path d="m12 3 9 5-9 5-9-5 9-5Zm-9 5v8l9 5 9-5V8m-9 5v8" /></>,
        search: <><circle cx="10.8" cy="10.8" r="6.8" /><path d="m16 16 4.5 4.5" /></>,
        logout: <><path d="M10 17l5-5-5-5m5 5H3m9-9h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7" /></>,
        calendar: <><path d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 0 1 1 1v13H4V6a1 1 0 0 1 1-1Z" /></>,
        arrow: <><path d="M5 12h14m-6-6 6 6-6 6" /></>,
        plus: <><path d="M12 5v14m-7-7h14" /></>,
        edit: <><path d="m16 4 4 4M4 20l4-.8L19 8a2.1 2.1 0 0 0-3-3L5 16l-1 4Z" /></>,
        trash: <><path d="M3 6h18m-2 0-.9 14H5.9L5 6m4 0V4h6v2m-5 4v6m4-6v6" /></>,
    };

    return <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor"
        strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{shapes[name]}</svg>;
}

function formatDate(value) {
    if (!value) return "—";
    const date = new Date(`${value.slice(0, 10)}T00:00:00`);
    return new Intl.DateTimeFormat("id-ID", { day: "numeric", month: "short", year: "numeric" }).format(date);
}

function formatDateTime(value) {
    if (!value) return "—";
    const date = new Date(value.replace(" ", "T"));
    return new Intl.DateTimeFormat("id-ID", {
        day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit",
    }).format(date);
}

function AdminApp() {
    const { admin, equipment, categories, users, csrfToken, loadError } = window.adminDashboardData;
    const [loans, setLoans] = useState(window.adminDashboardData.loans);
    const [loanRefreshError, setLoanRefreshError] = useState("");
    const [search, setSearch] = useState("");
    const [statusFilter, setStatusFilter] = useState("semua");
    const [editing, setEditing] = useState(null);
    const [passwordOpen, setPasswordOpen] = useState(false);

    useEffect(() => {
        let active = true;
        async function refreshLoans() {
            try {
                const response = await fetch("admin_loans.php", { credentials: "same-origin" });
                const data = await response.json();
                if (!response.ok) throw new Error(data.error || "Data peminjaman gagal diperbarui.");
                if (active) {
                    setLoans(data.loans);
                    setLoanRefreshError("");
                }
            } catch (error) {
                if (active) setLoanRefreshError(error.message || "Data peminjaman gagal diperbarui.");
            }
        }

        refreshLoans();
        const interval = window.setInterval(() => {
            if (!document.hidden) refreshLoans();
        }, 10000);
        return () => {
            active = false;
            window.clearInterval(interval);
        };
    }, []);

    const pendingCount = loadError ? "—" : loans.filter(loan =>
        loan.status === "menunggu_persetujuan" || loan.status === "menunggu_verifikasi_pengembalian"
    ).length;
    const filteredLoans = useMemo(() => {
        const term = search.trim().toLocaleLowerCase("id");
        return loans.filter(loan => {
            const matchesStatus = statusFilter === "semua" || loan.status === statusFilter;
            const searchable = `${loan.kode_peminjaman} ${loan.nama_peminjam} ${loan.username} ${loan.email || ""} ${loan.daftar_alat}`.toLocaleLowerCase("id");
            return matchesStatus && searchable.includes(term);
        });
    }, [loans, search, statusFilter]);

    return <div className="min-h-screen lg:flex">
        <aside className="sticky top-0 hidden h-screen w-[250px] shrink-0 overflow-y-auto border-r border-slate-200/80 bg-white px-5 py-7 lg:flex lg:flex-col">
            <a href="index.php" className="mb-12 flex items-center gap-3 px-2" aria-label="Pinjam.in Perangkat IT">
                <span className="grid h-10 w-10 place-items-center rounded-2xl bg-blue-600 text-white shadow-lg shadow-blue-600/20"><AdminIcon name="box" /></span>
                <span className="text-xl font-extrabold tracking-tight text-slate-900">pinjam<span className="text-blue-600">.in</span><span className="block text-[10px] font-semibold uppercase tracking-[.14em] text-slate-400">Perangkat IT</span></span>
            </a>
            <p className="mb-3 px-3 text-[10px] font-bold uppercase tracking-[.18em] text-slate-400">Administrasi</p>
            <nav className="space-y-1.5" aria-label="Navigasi admin">
                    <a href="#overview" className="flex items-center gap-3 rounded-xl bg-blue-50 px-3.5 py-3 text-sm font-semibold text-blue-700"><AdminIcon name="home" className="h-[18px] w-[18px]" />Ringkasan</a>
                    <a href="#peminjaman" className="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium text-slate-500 transition hover:bg-slate-50 hover:text-slate-900"><AdminIcon name="calendar" className="h-[18px] w-[18px]" />Peminjaman</a>
                    <a href="#perangkat" className="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium text-slate-500 transition hover:bg-slate-50 hover:text-slate-900"><AdminIcon name="box" className="h-[18px] w-[18px]" />Perangkat IT</a>
                    <a href="#akun" className="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium text-slate-500 transition hover:bg-slate-50 hover:text-slate-900"><AdminIcon name="users" className="h-[18px] w-[18px]" />Akun peminjam</a>
                    <a href="#laporan" className="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium text-slate-500 transition hover:bg-slate-50 hover:text-slate-900"><AdminIcon name="home" className="h-[18px] w-[18px]" />Laporan</a>
                    <a href="index.php" className="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium text-slate-500 transition hover:bg-slate-50 hover:text-slate-900"><AdminIcon name="box" className="h-[18px] w-[18px]" />Halaman publik</a>
            </nav>
            <div className="mt-2 rounded-2xl bg-blue-50 p-4">
                <div className="mb-3 grid h-9 w-9 place-items-center rounded-xl bg-white text-blue-600"><AdminIcon name="users" /></div>
                <p className="text-sm font-bold text-slate-800">Pantau peminjaman</p>
                <p className="mt-1 text-xs leading-5 text-slate-500">Data peminjam diambil langsung dari transaksi database.</p>
            </div>
        </aside>

        <main className="min-w-0 flex-1">
            <header className="sticky top-0 z-20 border-b border-slate-200/70 bg-white/90 backdrop-blur-xl">
                <div className="mx-auto flex h-[72px] max-w-[1440px] items-center justify-between px-5 sm:px-8 xl:px-10">
                    <div><p className="text-xs font-semibold text-slate-400 lg:hidden">Pinjam.in · Admin</p><p className="text-sm text-slate-500"><span className="hidden lg:inline">Dashboard / </span><span className="font-semibold text-slate-800">Data peminjaman</span></p></div>
                    <div className="flex items-center gap-3 sm:gap-4">
                        <div className="grid h-10 w-10 place-items-center rounded-full bg-blue-100 text-sm font-bold text-blue-700">{admin.nama.split(/\s+/).slice(0, 2).map(part => part[0]).join("").toUpperCase()}</div>
                        <div className="hidden sm:block"><p className="text-sm font-bold text-slate-800">{admin.nama}</p><p className="text-xs text-slate-400">Administrator</p></div>
                        <button type="button" onClick={() => setPasswordOpen(true)} className="rounded-lg px-2 py-2 text-xs font-semibold text-slate-500 hover:bg-slate-50 hover:text-blue-600">Ganti password</button>
                        <form method="post">
                            <input type="hidden" name="action" value="logout" /><input type="hidden" name="csrf_token" value={csrfToken} />
                            <button type="submit" title="Keluar" aria-label="Keluar dari dashboard" className="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-slate-500 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"><AdminIcon name="logout" /></button>
                        </form>
                    </div>
                </div>
                <nav className="flex gap-5 overflow-x-auto border-t border-slate-100 px-5 py-2.5 text-xs font-medium text-slate-500 scrollbar-hide lg:hidden" aria-label="Navigasi mobile">
                    <a href="#overview" className="whitespace-nowrap text-blue-700">Ringkasan</a><a href="#peminjaman" className="whitespace-nowrap">Peminjaman</a><a href="#perangkat" className="whitespace-nowrap">Perangkat</a><a href="#akun" className="whitespace-nowrap">Akun</a><a href="#laporan" className="whitespace-nowrap">Laporan</a>
                </nav>
            </header>

            <div className="mx-auto max-w-[1440px] px-5 py-7 sm:px-8 sm:py-9 xl:px-10">
                <section id="overview" className="relative isolate overflow-hidden rounded-[26px] bg-gradient-to-br from-[#004741] via-[#145e55] to-[#407d70] px-6 py-7 text-white shadow-xl shadow-[#004741]/10 sm:px-9 sm:py-9">
                    <div className="absolute -right-12 -top-24 -z-10 h-72 w-72 rounded-full border-[38px] border-white/10"></div>
                    <div className="absolute bottom-[-108px] right-[23%] -z-10 h-52 w-52 rounded-full bg-cyan-300/15 blur-2xl"></div>
                    <div className="relative max-w-2xl">
                        <div className="mb-4 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold text-blue-50"><span className="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>Dashboard admin</div>
                        <h1 className="text-[26px] font-extrabold tracking-tight sm:text-3xl">Halo, {admin.nama.split(" ")[0]}!</h1>
                        <p className="mt-2 max-w-lg text-sm leading-6 text-blue-100 sm:text-[15px]">Kelola perangkat IT, akun peminjam, dan proses pengajuan hingga pengembalian.</p>
                        <a href="#peminjaman" className="mt-6 inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-blue-700 shadow-sm transition hover:bg-blue-50">Lihat data peminjam <AdminIcon name="arrow" className="h-4 w-4" /></a>
                    </div>
                    <div className="absolute bottom-5 right-8 hidden text-7xl drop-shadow-lg lg:block" aria-hidden="true">📋</div>
                </section>

                <section className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3" aria-label="Ringkasan peminjaman">
                    <SummaryCard title="Total transaksi" value={loadError ? "—" : loans.length} note="seluruh pengajuan" color="blue" icon="users" />
                    <SummaryCard title="Perlu ditindaklanjuti" value={pendingCount} note="menunggu proses petugas" color="amber" icon="calendar" />
                    <SummaryCard title="Perangkat terdaftar" value={loadError ? "—" : equipment.length} note={`${users.length} akun peminjam`} color="violet" icon="box" />
                </section>

                <section id="peminjaman" className="mt-9 scroll-mt-28" aria-labelledby="loansHeading">
                    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                        <div><p className="text-xs font-bold uppercase tracking-[.16em] text-blue-600">Informasi transaksi</p><h2 id="loansHeading" className="mt-1.5 text-2xl font-extrabold tracking-tight text-slate-900">Siapa saja yang meminjam?</h2><p className="mt-1 text-sm text-slate-500">Nama peminjam, detail alat, dan status setiap pengajuan.</p><p className="mt-1 text-xs text-slate-400" aria-live="polite">{loanRefreshError || "Data diperbarui otomatis setiap 10 detik."}</p></div>
                        <label className="relative block w-full sm:max-w-[310px]">
                            <span className="sr-only">Cari peminjam atau alat</span><AdminIcon name="search" className="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-slate-400" />
                            <input value={search} onChange={event => setSearch(event.target.value)} type="search" placeholder="Cari nama, kode, atau alat..." className="h-11 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-4 text-sm outline-none transition placeholder:text-slate-400 focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                        </label>
                    </div>

                    <div className="mt-5 flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-slate-500"><span className="font-bold text-slate-800">{filteredLoans.length}</span> transaksi ditampilkan</p>
                        <label className="flex items-center gap-2 text-xs font-semibold text-slate-500">Filter status
                            <select value={statusFilter} onChange={event => setStatusFilter(event.target.value)} className="h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
                                <option value="semua">Semua status</option>
                                {Object.entries(loanStatuses).map(([value, status]) => <option key={value} value={value}>{status.label}</option>)}
                            </select>
                        </label>
                    </div>

                    <div className="mt-3 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm shadow-slate-900/[.02]">
                        {filteredLoans.length ? <div className="overflow-x-auto">
                            <table className="w-full min-w-[960px] text-left">
                                <thead className="border-b border-slate-100 bg-slate-50/80 text-[11px] font-bold uppercase tracking-[.08em] text-slate-500">
                                    <tr><th className="px-5 py-4">Peminjam</th><th className="px-5 py-4">Perangkat dipinjam</th><th className="px-5 py-4">Rencana pinjam</th><th className="px-5 py-4">Diajukan</th><th className="px-5 py-4">Status</th><th className="px-5 py-4">Tindakan</th></tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {filteredLoans.map(loan => {
                                        const status = loanStatuses[loan.status] || { label: loan.status, style: "bg-slate-100 text-slate-600 ring-slate-500/10" };
                                        return <tr key={loan.id} className="transition hover:bg-slate-50/70">
                                            <td className="px-5 py-4"><div className="flex items-center gap-3"><span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-blue-50 text-sm font-bold text-blue-700">{loan.nama_peminjam.split(/\s+/).slice(0, 2).map(part => part[0]).join("").toUpperCase()}</span><div><p className="font-bold text-slate-800">{loan.nama_peminjam}</p><p className="mt-0.5 text-xs text-slate-400">@{loan.username}{loan.email ? ` · ${loan.email}` : ""}</p><p className="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">{loan.kode_peminjaman}</p></div></div></td>
                                            <td className="max-w-[280px] px-5 py-4 text-sm leading-5 text-slate-700">{loan.daftar_alat}</td>
                                            <td className="px-5 py-4"><p className="whitespace-nowrap text-sm font-semibold text-slate-700">{formatDate(loan.tanggal_rencana_pinjam)}</p><p className="mt-1 whitespace-nowrap text-xs text-slate-400">s.d. {formatDate(loan.tanggal_rencana_kembali)}</p></td>
                                            <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{formatDateTime(loan.tanggal_pengajuan)}</td>
                                            <td className="px-5 py-4"><span className={`inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset ${status.style}`}>{status.label}</span></td>
                                            <td className="px-5 py-4">
                                                {({
                                                    menunggu_persetujuan: ["disetujui", "ditolak"],
                                                    disetujui: ["dipinjam"],
                                                    dipinjam: ["selesai"],
                                                    menunggu_verifikasi_pengembalian: ["dipinjam", "selesai"],
                                                }[loan.status] || []).length > 0 && <form method="post" className="flex items-center gap-2">
                                                    <input type="hidden" name="action" value="loan_status" /><input type="hidden" name="csrf_token" value={csrfToken} /><input type="hidden" name="loan_id" value={loan.id} />
                                                    <select name="status" defaultValue="" required className="h-9 rounded-lg border border-slate-200 bg-white px-2 text-xs">
                                                        <option value="" disabled>Ubah status</option>
                                                        {({
                                                            menunggu_persetujuan: ["disetujui", "ditolak"],
                                                            disetujui: ["dipinjam"],
                                                            dipinjam: ["selesai"],
                                                            menunggu_verifikasi_pengembalian: ["dipinjam", "selesai"],
                                                        }[loan.status] || []).map(next => <option key={next} value={next}>
                                                            {loan.status === "menunggu_verifikasi_pengembalian"
                                                                ? (next === "selesai" ? "Konfirmasi pengembalian" : "Pengembalian ditolak")
                                                                : loanStatuses[next].label}
                                                        </option>)}
                                                    </select>
                                                    <button type="submit" className="rounded-lg bg-blue-600 px-2.5 py-2 text-xs font-bold text-white hover:bg-blue-700">Simpan</button>
                                                </form>}
                                            </td>
                                        </tr>;
                                    })}
                                </tbody>
                            </table>
                        </div> : <div className="px-6 py-14 text-center"><span className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-slate-50 text-slate-400"><AdminIcon name="users" className="h-6 w-6" /></span><h3 className="mt-3 font-bold text-slate-800">{loadError ? "Data peminjaman gagal dimuat" : loans.length ? "Transaksi tidak ditemukan" : "Belum ada data peminjaman"}</h3><p className="mt-1 text-sm text-slate-500">{loadError || (loans.length ? "Coba ubah kata pencarian atau filter status." : "Data peminjam akan tampil setelah ada pengajuan di database.")}</p></div>}
                    </div>
                </section>

                <section id="perangkat" className="mt-10 scroll-mt-28" aria-labelledby="equipmentHeading">
                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                        <div><p className="text-xs font-bold uppercase tracking-[.16em] text-blue-600">Inventaris</p><h2 id="equipmentHeading" className="mt-1.5 text-2xl font-extrabold tracking-tight text-slate-900">Kelola perangkat IT</h2><p className="mt-1 text-sm text-slate-500">Tambah perangkat atau perbarui stok dan kondisinya.</p></div>
                        {editing && <button type="button" onClick={() => setEditing(null)} className="text-sm font-semibold text-slate-500 hover:text-slate-800">Batal mengubah</button>}
                    </div>
                    <form key={editing ? editing.id : "new"} method="post" encType="multipart/form-data" className="mt-5 grid gap-3 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                        <input type="hidden" name="action" value="equipment_save" /><input type="hidden" name="csrf_token" value={csrfToken} /><input type="hidden" name="equipment_id" value={editing ? editing.id : ""} />
                        <label className="text-xs font-semibold text-slate-600">Kode perangkat<input name="kode_alat" required maxLength="30" defaultValue={editing ? editing.kode_alat : ""} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm font-normal" /></label>
                        <label className="text-xs font-semibold text-slate-600">Nama perangkat<input name="nama" required maxLength="120" defaultValue={editing ? editing.nama : ""} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm font-normal" /></label>
                        <label className="text-xs font-semibold text-slate-600">Kategori<select name="kategori_id" required defaultValue={editing ? editing.kategori_id : ""} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal"><option value="" disabled>Pilih kategori</option>{categories.map(category => <option key={category.id} value={category.id}>{category.nama}</option>)}</select></label>
                        <label className="text-xs font-semibold text-slate-600">Stok total<input name="stok_total" type="number" min={0} required defaultValue={editing ? editing.stok_total : 1} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm font-normal" /></label>
                        <label className="text-xs font-semibold text-slate-600 sm:col-span-2">Deskripsi<input name="deskripsi" maxLength="2000" defaultValue={editing ? editing.deskripsi : ""} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm font-normal" /></label>
                        <label className="text-xs font-semibold text-slate-600 sm:col-span-2">Foto perangkat (JPG, PNG, WebP · maks. 5 MB)<input name="gambar" type="file" accept="image/jpeg,image/png,image/webp" className="mt-1.5 block w-full text-xs font-normal file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-semibold file:text-blue-700" />{editing && editing.gambar && <img src={editing.gambar} alt={`Foto ${editing.nama}`} className="mt-2 h-16 w-24 rounded-lg border border-slate-200 object-cover" />}</label>
                        <label className="text-xs font-semibold text-slate-600">Kondisi<select name="kondisi" defaultValue={editing ? editing.kondisi : "baik"} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal"><option value="baik">Baik</option><option value="rusak">Rusak</option><option value="perawatan">Perawatan</option></select></label>
                        <label className="text-xs font-semibold text-slate-600">Status<select name="status" defaultValue={editing ? editing.status : "aktif"} className="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal"><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option></select></label>
                        <button type="submit" className="inline-flex h-10 items-center justify-center gap-2 self-end rounded-lg bg-blue-600 px-4 text-sm font-bold text-white hover:bg-blue-700"><AdminIcon name="plus" className="h-4 w-4" />{editing ? "Simpan perubahan" : "Tambah perangkat"}</button>
                    </form>
                    <div className="mt-4 overflow-x-auto rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                        <table className="w-full min-w-[760px] text-left text-sm">
                            <thead className="border-b border-slate-100 bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500"><tr><th className="px-4 py-3">Perangkat</th><th className="px-4 py-3">Kategori</th><th className="px-4 py-3">Stok</th><th className="px-4 py-3">Kondisi / status</th><th className="px-4 py-3">Aksi</th></tr></thead>
                            <tbody className="divide-y divide-slate-100">{equipment.map(item => <tr key={item.id}>
                                <td className="px-4 py-3"><div className="flex items-center gap-3">{item.gambar && <img src={item.gambar} alt="" className="h-10 w-14 rounded-md border border-slate-200 object-cover" />}<div><p className="font-bold text-slate-800">{item.nama}</p><p className="text-xs text-slate-400">{item.kode_alat}</p></div></div></td>
                                <td className="px-4 py-3 text-slate-600">{item.kategori}</td>
                                <td className="px-4 py-3 text-slate-600">{item.stok_tersedia} tersedia / {item.stok_total} total</td>
                                <td className="px-4 py-3 text-slate-600">{item.kondisi} · {item.status}</td>
                                <td className="px-4 py-3"><div className="flex gap-2">
                                    <button type="button" onClick={() => { setEditing(item); document.getElementById("perangkat").scrollIntoView({ behavior: "smooth" }); }} className="rounded-lg border border-slate-200 px-2.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">Ubah</button>
                                    <form method="post" onSubmit={event => { if (!window.confirm(`Hapus ${item.nama}?`)) event.preventDefault(); }}><input type="hidden" name="action" value="equipment_delete" /><input type="hidden" name="csrf_token" value={csrfToken} /><input type="hidden" name="equipment_id" value={item.id} /><button type="submit" className="rounded-lg border border-rose-200 px-2.5 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50">Hapus</button></form>
                                </div></td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                </section>

                <section id="akun" className="mt-10 scroll-mt-28" aria-labelledby="usersHeading">
                    <div><p className="text-xs font-bold uppercase tracking-[.16em] text-blue-600">Pengguna</p><h2 id="usersHeading" className="mt-1.5 text-2xl font-extrabold tracking-tight text-slate-900">Akun peminjam</h2><p className="mt-1 text-sm text-slate-500">Lihat akun terdaftar dan aktifkan atau nonaktifkan aksesnya.</p></div>
                    <div className="mt-4 overflow-x-auto rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                        <table className="w-full min-w-[620px] text-left text-sm">
                            <thead className="border-b border-slate-100 bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500"><tr><th className="px-4 py-3">Nama</th><th className="px-4 py-3">Username</th><th className="px-4 py-3">Email</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Aksi</th></tr></thead>
                            <tbody className="divide-y divide-slate-100">{users.map(account => <tr key={account.id}>
                                <td className="px-4 py-3 font-semibold text-slate-800">{account.nama}</td><td className="px-4 py-3 text-slate-600">@{account.username}</td><td className="px-4 py-3 text-slate-600">{account.email || "—"}</td><td className="px-4 py-3">{account.status}</td>
                                <td className="px-4 py-3"><form method="post"><input type="hidden" name="action" value="user_status" /><input type="hidden" name="csrf_token" value={csrfToken} /><input type="hidden" name="user_id" value={account.id} /><input type="hidden" name="status" value={account.status === "aktif" ? "nonaktif" : "aktif"} /><button type="submit" className="rounded-lg border border-slate-200 px-2.5 py-2 text-xs font-semibold hover:bg-slate-50">{account.status === "aktif" ? "Nonaktifkan" : "Aktifkan"}</button></form></td>
                            </tr>)}</tbody>
                        </table>
                        {!users.length && <p className="p-6 text-center text-sm text-slate-500">Belum ada akun peminjam.</p>}
                    </div>
                </section>

                <section id="laporan" className="mt-10 scroll-mt-28" aria-labelledby="reportsHeading">
                    <div><p className="text-xs font-bold uppercase tracking-[.16em] text-blue-600">Laporan</p><h2 id="reportsHeading" className="mt-1.5 text-2xl font-extrabold tracking-tight text-slate-900">Ringkasan peminjaman</h2><p className="mt-1 text-sm text-slate-500">Rekap status dari seluruh transaksi yang tercatat.</p></div>
                    <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {Object.entries(loanStatuses).map(([key, status]) => <article key={key} className="rounded-2xl border border-slate-200/80 bg-white p-4"><p className="text-xs text-slate-500">{status.label}</p><p className="mt-1 text-2xl font-extrabold text-slate-900">{loans.filter(loan => loan.status === key).length}</p></article>)}
                    </div>
                </section>
                {passwordOpen && <div className="fixed inset-0 z-40 grid place-items-center overflow-y-auto bg-slate-900/40 p-4 backdrop-blur-sm">
                    <section role="dialog" aria-modal="true" aria-labelledby="passwordHeading" className="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
                        <div className="flex items-start justify-between"><div><p className="text-xs font-bold uppercase tracking-[.15em] text-blue-600">Keamanan akun</p><h2 id="passwordHeading" className="mt-1 text-xl font-extrabold text-slate-900">Ganti password admin</h2></div><button type="button" onClick={() => setPasswordOpen(false)} className="grid h-9 w-9 place-items-center rounded-xl text-slate-400 hover:bg-slate-100" aria-label="Tutup">×</button></div>
                        <form method="post" className="mt-5 space-y-4">
                            <input type="hidden" name="action" value="password_change" /><input type="hidden" name="csrf_token" value={csrfToken} />
                            <label className="block text-xs font-semibold text-slate-600">Password saat ini<input name="current_password" type="password" required autoComplete="current-password" className="mt-1.5 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm font-normal" /></label>
                            <label className="block text-xs font-semibold text-slate-600">Password baru<input name="new_password" type="password" required minLength={8} autoComplete="new-password" className="mt-1.5 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm font-normal" /><span className="mt-1 block text-[11px] font-normal text-slate-400">Minimal 8 karakter.</span></label>
                            <label className="block text-xs font-semibold text-slate-600">Ulangi password baru<input name="password_confirmation" type="password" required minLength={8} autoComplete="new-password" className="mt-1.5 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm font-normal" /></label>
                            <button type="submit" className="h-11 w-full rounded-xl bg-blue-600 px-4 text-sm font-bold text-white hover:bg-blue-700">Simpan password baru</button>
                        </form>
                    </section>
                </div>}
                <footer className="pb-3 pt-8 text-center text-xs text-slate-400">© {new Date().getFullYear()} Pinjam.in · Dashboard Administrator</footer>
            </div>
        </main>
    </div>;
}

function SummaryCard({ title, value, note, color, icon }) {
    const colorClass = {
        blue: "bg-blue-50 text-blue-600",
        amber: "bg-amber-50 text-amber-600",
        violet: "bg-violet-50 text-violet-600",
    };
    return <article className="flex items-center gap-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm shadow-slate-900/[.02]">
        <span className={`grid h-12 w-12 shrink-0 place-items-center rounded-2xl ${colorClass[color]}`}><AdminIcon name={icon} className="h-6 w-6" /></span>
        <div><p className="text-xs font-medium text-slate-500">{title}</p><p className="mt-0.5 text-2xl font-extrabold tracking-tight text-slate-900">{value}</p><p className="mt-0.5 text-xs text-slate-400">{note}</p></div>
    </article>;
}

ReactDOM.createRoot(document.getElementById("admin-root")).render(<AdminApp />);
