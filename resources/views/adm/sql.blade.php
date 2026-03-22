<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">SQL Importer</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Path + last import info --}}
            <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg text-sm flex items-center justify-between gap-4">
                <code class="font-mono">{{ $path }}</code>
                <span id="last-import" class="text-blue-600 text-xs whitespace-nowrap shrink-0">
                    {{ $lastImport ? 'Last import: ' . $lastImport : 'No imports yet' }}
                </span>
            </div>

            {{-- File List --}}
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">

                {{-- Toolbar --}}
                <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3 flex-wrap">
                    @if(!empty($files))
                        <button id="btn-install-all" onclick="installAll()"
                            class="bg-red-600 hover:bg-red-500 text-white text-sm font-semibold px-5 py-2 rounded transition">
                            Install All ({{ count($files) }})
                        </button>
                        <button id="btn-install-selected" onclick="installSelected()"
                            class="bg-gray-600 hover:bg-gray-500 text-white text-sm font-semibold px-5 py-2 rounded transition">
                            Install Selected
                        </button>
                        <label class="flex items-center gap-2 text-sm text-gray-500 cursor-pointer ml-auto">
                            <input type="checkbox" id="select-all" onchange="toggleAll(this.checked)" class="rounded">
                            Select All
                        </label>
                    @endif
                </div>

                {{-- Overall progress bar --}}
                <div id="overall-status" class="hidden px-6 py-3 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                            <div id="overall-bar" class="bg-red-500 h-2 rounded-full transition-all duration-300" style="width:0%"></div>
                        </div>
                        <span id="overall-label" class="text-xs text-gray-500 whitespace-nowrap w-48 text-right"></span>
                    </div>
                </div>

                @if(empty($files))
                    <div class="px-6 py-8 text-center text-gray-400 text-sm">
                        No .sql files found in the import directory.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-3 w-8"></th>
                                    <th class="px-4 py-3 text-left">Table</th>
                                    <th class="px-4 py-3 text-right">Size</th>
                                    <th class="px-4 py-3 text-right">Last Update</th>
                                    <th class="px-4 py-3 text-right">Game Date</th>
                                    <th class="px-4 py-3 text-center w-44">Status</th>
                                    <th class="px-4 py-3 text-center w-20">Action</th>
                                </tr>
                            </thead>
                            <tbody id="file-table-body" class="divide-y divide-gray-100">
                                @foreach($files as $file)
                                @php
                                    $table = preg_replace('/\.mysql\.sql$/i', '', $file['name']);
                                @endphp
                                <tr id="row-{{ Str::slug($file['name']) }}" class="hover:bg-gray-50 transition-opacity" data-file="{{ $file['name'] }}">
                                    <td class="px-4 py-2.5 text-center">
                                        <input type="checkbox" class="file-check rounded" value="{{ $file['name'] }}">
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span class="font-medium text-gray-800">{{ $table }}</span><span class="text-gray-400">.mysql.sql</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-gray-500 whitespace-nowrap">
                                        @if($file['size'] >= 1048576)
                                            {{ number_format($file['size'] / 1048576, 1) }} MB
                                        @else
                                            {{ number_format($file['size'] / 1024, 1) }} KB
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-gray-400 text-xs meta-export whitespace-nowrap">…</td>
                                    <td class="px-4 py-2.5 text-right text-gray-400 text-xs meta-game whitespace-nowrap">…</td>
                                    <td class="px-4 py-2.5 text-center w-44">
                                        <span class="row-idle text-gray-300 text-xs">—</span>
                                        <div class="row-progress hidden">
                                            <div class="flex items-center gap-2 justify-center">
                                                <div class="w-20 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                                    <div class="row-bar bg-red-500 h-1.5 rounded-full transition-all duration-200" style="width:0%"></div>
                                                </div>
                                                <span class="row-pct text-xs text-gray-500 w-8 text-right">0%</span>
                                            </div>
                                        </div>
                                        <span class="row-done hidden text-xs font-semibold"></span>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <button
                                            onclick="runOne('{{ addslashes($file['name']) }}', {{ $file['size'] }})"
                                            class="run-btn text-xs text-red-600 hover:text-red-500 font-semibold"
                                            data-file="{{ $file['name'] }}"
                                            data-size="{{ $file['size'] }}">
                                            Run
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Error log --}}
            <div id="error-panel" class="hidden bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Errors</h3>
                <div id="error-log" class="font-mono text-xs bg-gray-950 text-yellow-400 rounded-lg p-4 max-h-48 overflow-y-auto space-y-0.5"></div>
            </div>

        </div>
    </div>

    <script>
        const processUrl = "{{ route('adm.sql.process') }}";
        const metaUrl    = "{{ route('adm.sql.meta') }}";
        const csrf       = "{{ csrf_token() }}";

        // Load meta (export date + game date) on page load
        async function loadMeta() {
            try {
                const resp = await fetch(metaUrl);
                if (!resp.ok) return;
                const meta = await resp.json();
                document.querySelectorAll('#file-table-body tr').forEach(row => {
                    const m = meta[row.dataset.file];
                    if (!m) return;
                    row.querySelector('.meta-export').textContent = m.export_date ?? '—';
                    row.querySelector('.meta-game').textContent   = m.game_date   ?? '—';
                });
            } catch (e) {}
        }
        document.addEventListener('DOMContentLoaded', loadMeta);

        function toggleAll(checked) {
            document.querySelectorAll('.file-check').forEach(cb => cb.checked = checked);
        }

        function getCheckedFiles() {
            return [...document.querySelectorAll('.file-check:checked')].map(cb => ({
                name: cb.value,
                size: parseInt(document.querySelector(`.run-btn[data-file="${cb.value}"]`)?.dataset.size ?? 0),
            }));
        }

        function allFiles() {
            return [...document.querySelectorAll('.run-btn')].map(b => ({
                name: b.dataset.file,
                size: parseInt(b.dataset.size),
            }));
        }

        function setButtonsDisabled(disabled) {
            document.querySelectorAll('.run-btn, #btn-install-all, #btn-install-selected, .file-check, #select-all')
                .forEach(b => b.disabled = disabled);
        }

        async function installAll() {
            await runFiles(allFiles());
        }

        async function installSelected() {
            const files = getCheckedFiles();
            if (!files.length) { alert('No files selected.'); return; }
            await runFiles(files);
        }

        async function runOne(filename, fileSize) {
            await runFiles([{ name: filename, size: fileSize }]);
        }

        async function runFiles(files) {
            setButtonsDisabled(true);
            document.getElementById('error-log').innerHTML = '';
            document.getElementById('error-panel').classList.add('hidden');

            document.getElementById('overall-status').style.display = 'block';
            let overallErrors = 0;

            for (let i = 0; i < files.length; i++) {
                showOverall(i, files.length, files[i].name);
                const errors = await importFile(files[i].name, files[i].size);
                overallErrors += errors;
                if (errors > 0) break;
            }

            document.getElementById('overall-bar').style.width = '100%';
            document.getElementById('overall-label').textContent =
                overallErrors === 0 ? `✅ All ${files.length} done` : `⚠ Stopped — see errors below`;

            // Update last import timestamp
            if (overallErrors === 0) {
                const now = new Date();
                const ts  = now.toISOString().replace('T', ' ').substring(0, 19);
                document.getElementById('last-import').textContent = 'Last import: ' + ts;
            }

            setButtonsDisabled(false);
        }

        function showOverall(done, total, currentFile) {
            const pct   = total > 0 ? Math.round((done / total) * 100) : 0;
            document.getElementById('overall-bar').style.width   = pct + '%';
            document.getElementById('overall-label').textContent = `${done + 1}/${total} — ${currentFile}`;
        }

        async function importFile(filename, fileSize) {
            const row      = document.querySelector(`[data-file="${filename}"]`);
            const idleEl   = row?.querySelector('.row-idle');
            const progEl   = row?.querySelector('.row-progress');
            const barEl    = row?.querySelector('.row-bar');
            const pctEl    = row?.querySelector('.row-pct');
            const doneEl   = row?.querySelector('.row-done');

            idleEl?.classList.add('hidden');
            doneEl?.classList.add('hidden');
            progEl?.classList.remove('hidden');
            if (barEl) barEl.style.width = '0%';
            if (pctEl) pctEl.textContent  = '0%';

            let offset      = 0;
            let totalErrors = 0;

            while (true) {
                let resp, data;
                try {
                    resp = await fetch(processUrl, {
                        method:  'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body:    JSON.stringify({ file: filename, offset }),
                    });
                    data = await resp.json();
                } catch (e) {
                    logError(`[${filename}] Network error: ${e.message}`);
                    totalErrors++;
                    break;
                }

                if (!resp.ok) {
                    logError(`[${filename}] ${data.error ?? resp.statusText}`);
                    totalErrors++;
                    break;
                }

                const pct = fileSize > 0 ? Math.min(100, Math.round((data.offset / fileSize) * 100)) : 0;
                if (barEl) barEl.style.width = pct + '%';
                if (pctEl) pctEl.textContent  = pct + '%';

                if (data.errors?.length) {
                    totalErrors += data.errors.length;
                    data.errors.forEach(e => logError(`[${filename}] ${e}`));
                }

                offset = data.offset;

                if (data.done) {
                    progEl?.classList.add('hidden');
                    doneEl?.classList.remove('hidden');
                    if (totalErrors === 0) {
                        doneEl.textContent = data.deleted ? '✅ deleted' : '✅ done';
                        doneEl.className   = 'row-done text-xs font-semibold text-green-600';
                        // Fade out row on production (file was deleted)
                        if (data.deleted && row) {
                            setTimeout(() => {
                                row.style.transition = 'opacity 0.6s';
                                row.style.opacity    = '0';
                                setTimeout(() => row.remove(), 650);
                            }, 1200);
                        }
                    } else {
                        doneEl.textContent = `⚠ ${totalErrors} error(s)`;
                        doneEl.className   = 'row-done text-xs font-semibold text-yellow-600';
                    }
                    break;
                }

                await new Promise(r => setTimeout(r, 10));
            }

            return totalErrors;
        }

        function logError(msg) {
            document.getElementById('error-panel').classList.remove('hidden');
            const log  = document.getElementById('error-log');
            const line = document.createElement('div');
            line.textContent = msg;
            log.appendChild(line);
            log.scrollTop = log.scrollHeight;
        }
    </script>
</x-app-layout>
