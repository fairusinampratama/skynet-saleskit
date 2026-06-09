@extends('technician.layout', ['title' => $registration ? __('registration.title.edit') : __('registration.title.new')])

@php
    $hasEvidence = filled($registration?->location_photo_path);
    $hasKtpDocument = filled($registration?->ktp_photo_path);
    $existingKtpUrl = $hasKtpDocument ? \Illuminate\Support\Facades\Storage::disk('public')->url($registration->ktp_photo_path) : '';
@endphp

@section('content')
    <form
        id="registrationForm"
        class="relative -mx-3 -mt-4 grid gap-4 overflow-hidden px-3 pb-28 pt-4 md:mx-0 md:mt-0 md:pb-0 md:pt-0"
        method="POST"
        enctype="multipart/form-data"
        action="{{ $registration ? route('technician.registrations.update', $registration) : route('technician.registrations.store') }}"
        data-registration-form
        data-scan-ktp-url="{{ route('technician.registrations.scan-ktp', [], false) }}"
        data-existing-evidence="{{ $hasEvidence ? '1' : '0' }}"
        data-existing-ktp-document="{{ $hasKtpDocument ? '1' : '0' }}"
        data-existing-ktp-url="{{ $existingKtpUrl }}"
    >
        @csrf
        @if ($registration)
            @method('PUT')
        @endif

        <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[560px] tech-portal-shell tech-portal-grid"></div>

        <section class="relative overflow-hidden rounded-3xl border border-white/10 bg-slate-950 px-4 py-5 text-white shadow-[0_24px_70px_rgb(15_23_42_/_0.22)] tech-dark-grid md:px-6">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgb(14_165_233_/_0.35),transparent_34rem),radial-gradient(circle_at_bottom_right,rgb(245_158_11_/_0.28),transparent_30rem)]"></div>
            <div class="relative grid gap-5 lg:grid-cols-[1fr_360px] lg:items-end">
                <div>
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1 font-mono text-[10px] font-black uppercase tracking-widest text-amber-200">
                        <x-heroicon-o-wrench-screwdriver class="h-4 w-4" />
                        Portal Teknisi Lapangan
                    </div>
                    <h1 class="font-display text-3xl font-black leading-tight md:text-4xl">{{ $registration ? __('registration.title.edit') : __('registration.title.new') }}</h1>
                    <p class="mt-2 max-w-2xl text-sm font-semibold leading-6 text-slate-300">Mengambil memo Work Order aktif, pindai KTP digital, kunci GPS pemasangan, dan kirim data onboarding GPON ke review admin.</p>
                    <div class="mt-4 flex flex-wrap gap-2 font-mono text-[10px] font-black uppercase tracking-wider text-slate-300">
                        <span class="rounded-full border border-white/10 bg-white/10 px-2.5 py-1">OCR KTP</span>
                        <span class="rounded-full border border-white/10 bg-white/10 px-2.5 py-1">GPS Auto-Lock</span>
                        <span class="rounded-full border border-white/10 bg-white/10 px-2.5 py-1">Draft Terenkripsi</span>
                    </div>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-mono text-[10px] font-black uppercase tracking-widest text-slate-400">Tahap Onboarding</div>
                            <p class="mt-1 text-sm font-bold text-white">{{ $registration ? ($registration->updated_at?->format('d M Y H:i') ?? 'Draf tersimpan') : 'Sesi input baru' }}</p>
                        </div>
                        @if ($registration)
                            <x-tech.status-badge style="portal" variant="dark">{{ __('registration.status.'.$registration->status) }}</x-tech.status-badge>
                        @else
                            <x-tech.status-badge style="portal" variant="dark">Draf Baru</x-tech.status-badge>
                        @endif
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <x-tech.summary-tile style="portal" label="Field wajib" value="0/0" value-id="requiredProgress" />
                        <x-tech.summary-tile style="portal" label="OCR KTP" value="Menunggu" value-id="ocrSummary" />
                        <x-tech.summary-tile style="portal" label="GPS" value="Manual" value-id="gpsSummary" />
                        <x-tech.summary-tile style="portal" label="Foto" value="Opsional" value-id="evidenceSummary" />
                    </div>
                </div>
            </div>
        </section>

        <x-tech.step-nav style="portal" aria-label="Bagian registrasi">
            <x-tech.step-tab style="portal" active data-step-target="ktp">01 KTP/OCR</x-tech.step-tab>
            <x-tech.step-tab style="portal" data-step-target="customer">02 Pelanggan</x-tech.step-tab>
            <x-tech.step-tab style="portal" data-step-target="address">03 Alamat</x-tech.step-tab>
            <x-tech.step-tab style="portal" data-step-target="evidence">04 GPS & Foto</x-tech.step-tab>
            <x-tech.step-tab style="portal" data-step-target="review">05 Dispatch</x-tech.step-tab>
        </x-tech.step-nav>

        <x-tech.panel style="portal" id="step-ktp" class="registration-step scroll-mt-28" data-step="ktp">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600">
                        <x-heroicon-o-identification class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="font-mono text-[10px] font-black uppercase tracking-widest text-slate-400">Langkah 1 / Identitas</div>
                        <h2 class="font-display text-lg font-black text-slate-950">Pemindai KTP Pelanggan</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Ambil foto terang dan rata agar OCR membantu mengisi NIK, nama, dan alamat KTP.</p>
                    </div>
                </div>
                <x-tech.status-badge style="portal" variant="warn" data-step-status="ktp">{{ __('ui.common.needed') }}</x-tech.status-badge>
            </div>

            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                <div class="grid gap-3">
                    <div id="ktpFrame" class="relative aspect-[1.58/1] overflow-hidden rounded-2xl border border-slate-800 bg-slate-950 shadow-inner">
                        <img id="ktpPreview" class="hidden h-full w-full object-contain" alt="Pratinjau foto KTP" @if ($existingKtpUrl) src="{{ $existingKtpUrl }}" @endif>
                        <div class="pointer-events-none absolute inset-x-0 top-0 h-1/2 tech-ktp-scanline"></div>
                        <div id="ktpFramePlaceholder" class="absolute inset-0 z-10 grid place-items-center gap-3 p-6 text-center">
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-3">
                                <x-heroicon-o-identification class="h-10 w-10 text-slate-300" />
                            </div>
                            <div>
                                <p class="font-display text-lg font-black text-slate-100">POSISIKAN KTP/SIM</p>
                                <p class="mt-1 text-sm font-semibold text-slate-400">Ambil atau unggah foto, periksa hasilnya, lalu baca teks KTP.</p>
                            </div>
                        </div>
                    </div>
                    <canvas id="ktpCanvas" hidden></canvas>
                    <input id="processedKtp" name="processed_ktp_image" type="hidden">
                    <input id="ocrFieldSources" name="ocr_field_sources" type="hidden">
                    <p id="ktpScanStatus" class="rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800" role="status">Ambil atau unggah foto KTP untuk mulai.</p>
                    <input class="sr-only" name="ktp_image" type="file" id="ktpCameraInput" accept="image/*" capture="environment">
                    <input class="sr-only" type="file" id="ktpUploadInput" accept="image/*">
                </div>

                <div class="grid content-start gap-3 rounded-2xl border border-slate-200 bg-slate-50/80 p-3">
                    <div class="font-mono text-[10px] font-black uppercase tracking-widest text-slate-400">Aksi OCR KTP</div>
                    <div class="grid gap-2" data-ktp-actions-empty>
                        <x-tech.button style="portal" variant="primary" type="button" id="startCamera" icon="camera" full>Ambil Foto KTP</x-tech.button>
                        <x-tech.button style="portal" variant="secondary" type="button" id="uploadKtp" icon="arrow-up-tray" full>Unggah</x-tech.button>
                    </div>
                    <div class="hidden grid gap-2" data-ktp-actions-ready>
                        <x-tech.button style="portal" variant="primary" type="button" id="scanKtpText" icon="document-magnifying-glass" full disabled>Baca Teks KTP</x-tech.button>
                        <div class="grid grid-cols-2 gap-2">
                            <x-tech.button style="portal" variant="secondary" type="button" id="retakeKtpInline" icon="arrow-path" full>Foto Ulang</x-tech.button>
                            <x-tech.button style="portal" variant="secondary" type="button" id="uploadKtpReady" icon="arrow-up-tray" full>Unggah</x-tech.button>
                        </div>
                    </div>
                </div>
            </div>
        </x-tech.panel>

        <x-tech.panel style="portal" id="step-customer" class="registration-step scroll-mt-28" data-step="customer">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-600">
                        <x-heroicon-o-user class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="font-mono text-[10px] font-black uppercase tracking-widest text-slate-400">Langkah 2 / Profil</div>
                        <h2 class="font-display text-lg font-black text-slate-950">Profil Pelanggan Terkait</h2>
                    </div>
                </div>
                <x-tech.status-badge style="portal" variant="warn" data-step-status="customer">{{ __('ui.common.incomplete') }}</x-tech.status-badge>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                <x-tech.field style="portal" label="Nama Pelanggan" name="name" :value="old('name', $registration?->name)" maxlength="255" autocomplete="name" data-required-field />
                <x-tech.field style="portal" label="NIK" name="nik" :value="old('nik', $registration?->nik)" inputmode="numeric" minlength="16" maxlength="16" pattern="[0-9]{16}" autocomplete="off" data-required-field />
                <x-tech.field style="portal" label="Nomor Telepon" name="phone" :value="old('phone', $registration?->phone)" inputmode="tel" maxlength="30" pattern="\+?[0-9][0-9\s().-]{7,29}" autocomplete="tel" data-required-field />
                <x-tech.select style="portal" label="Paket" name="package" data-required-field>
                    <option value="">Pilih paket</option>
                    @foreach (\App\Models\Registration::PACKAGES as $package)
                        <option value="{{ $package }}" @selected(old('package', $registration?->package) === $package)>{{ $package }}</option>
                    @endforeach
                </x-tech.select>
            </div>
        </x-tech.panel>

        <x-tech.panel style="portal" id="step-address" class="registration-step scroll-mt-28" data-step="address">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-600">
                        <x-heroicon-o-map class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="font-mono text-[10px] font-black uppercase tracking-widest text-slate-400">Langkah 3 / Alamat</div>
                        <h2 class="font-display text-lg font-black text-slate-950">Alamat Resmi & Pemasangan</h2>
                    </div>
                </div>
                <x-tech.status-badge style="portal" variant="warn" data-step-status="address">{{ __('ui.common.incomplete') }}</x-tech.status-badge>
            </div>
            <div class="grid gap-3">
                <x-tech.select style="portal" label="Area" name="area_id" data-required-field>
                    <option value="">Pilih area</option>
                    @foreach ($areas as $area)
                        <option value="{{ $area->id }}" @selected((string) old('area_id', $registration?->area_id) === (string) $area->id)>
                            {{ $area->name }}
                        </option>
                    @endforeach
                </x-tech.select>
                <div class="grid gap-3 md:grid-cols-2">
                    <x-tech.textarea style="portal" label="Alamat KTP" name="ktp_full_address" maxlength="2000">{{ old('ktp_full_address', $registration?->ktp_full_address) }}</x-tech.textarea>
                    <x-tech.textarea style="portal" label="Alamat Instalasi" name="installation_full_address" maxlength="2000" data-required-field>{{ old('installation_full_address', $registration?->installation_full_address) }}</x-tech.textarea>
                </div>
            </div>
            <div class="mt-3">
                <x-tech.button style="portal" variant="ghost" type="button" id="copyKtpAddress" icon="clipboard-document">{{ __('ui.actions.copy_ktp_address') }}</x-tech.button>
            </div>
        </x-tech.panel>

        <x-tech.panel style="portal" id="step-evidence" class="registration-step scroll-mt-28" data-step="evidence">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600">
                        <x-heroicon-o-map-pin class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="font-mono text-[10px] font-black uppercase tracking-widest text-slate-400">Langkah 4 / Lapangan</div>
                        <h2 class="font-display text-lg font-black text-slate-950">Satelit GPS Live & Bukti Lokasi</h2>
                    </div>
                </div>
                <x-tech.status-badge style="portal" variant="warn" data-step-status="evidence">{{ __('ui.common.incomplete') }}</x-tech.status-badge>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                <x-tech.field style="portal" label="Latitude" name="latitude" id="latitude" type="number" :value="old('latitude', $registration?->latitude)" inputmode="decimal" step="any" min="-90" max="90" data-required-field />
                <x-tech.field style="portal" label="Longitude" name="longitude" id="longitude" type="number" :value="old('longitude', $registration?->longitude)" inputmode="decimal" step="any" min="-180" max="180" data-required-field />
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50/80 p-3">
                <x-tech.button style="portal" variant="secondary" type="button" id="captureGps" icon="map-pin">{{ __('ui.actions.use_current_gps') }}</x-tech.button>
                <span id="gpsStatus" class="text-sm font-semibold text-slate-500" role="status">Gunakan GPS atau isi koordinat secara manual.</span>
            </div>
            <div class="mt-3 grid gap-3">
                <x-tech.field style="portal" label="Foto Rumah / Lokasi (opsional)" name="location_photo" type="file" id="locationPhoto" accept="image/*" capture="environment" data-max-size-bytes="20971520" />
                <input id="processedLocationPhoto" name="processed_location_photo" type="hidden">
                <p id="locationPhotoStatus" class="-mt-1 rounded-2xl border border-slate-200 bg-white/70 px-3 py-2 text-sm font-semibold text-slate-500" role="status">Batas foto rumah 20 MB. Foto besar akan diperkecil sebelum dikirim.</p>
                <x-tech.textarea style="portal" label="Catatan Teknisi" name="technician_notes" maxlength="2000">{{ old('technician_notes', $registration?->technician_notes) }}</x-tech.textarea>
            </div>
        </x-tech.panel>

        <x-tech.panel style="portal" id="step-review" class="registration-step scroll-mt-28" data-step="review">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-900 text-white">
                        <x-heroicon-o-clipboard-document-check class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="font-mono text-[10px] font-black uppercase tracking-widest text-slate-400">Langkah 5 / Dispatch</div>
                        <h2 class="font-display text-lg font-black text-slate-950">Tinjau & Kirim Work Order</h2>
                    </div>
                </div>
                <x-tech.status-badge style="portal" variant="warn" id="reviewStatus">Tinjau</x-tech.status-badge>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                    <div class="font-mono text-[10px] font-black uppercase tracking-widest text-slate-400">Pelanggan</div>
                    <strong id="reviewCustomer" class="mt-2 block text-base font-black text-slate-950">Pelanggan belum diisi</strong>
                    <span id="reviewContact" class="mt-1 block text-sm font-semibold text-slate-500">Telepon dan area akan tampil di sini.</span>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                    <div class="font-mono text-[10px] font-black uppercase tracking-widest text-slate-400">Checklist</div>
                    <strong class="mt-2 block text-base font-black text-slate-950">Registrasi teknisi</strong>
                    <span id="reviewChecklist" class="mt-1 block text-sm font-semibold text-slate-500">Lengkapi field wajib sebelum mengirim.</span>
                </div>
            </div>
        </x-tech.panel>

        <div class="hidden flex-wrap gap-2 md:flex">
            <x-tech.button style="portal" variant="secondary" type="submit" name="action" value="draft" icon="archive-box">{{ __('ui.actions.save_draft') }}</x-tech.button>
            <x-tech.button style="portal" type="submit" name="action" value="submit" icon="paper-airplane">{{ __('ui.actions.submit_review') }}</x-tech.button>
        </div>

        <x-tech.mobile-action-bar style="portal">
            <x-tech.button style="portal" variant="secondary" type="submit" name="action" value="draft" icon="archive-box" full>{{ __('ui.actions.save_draft') }}</x-tech.button>
            <x-tech.button style="portal" type="button" id="mobilePrimaryAction" full>Lanjut</x-tech.button>
        </x-tech.mobile-action-bar>
    </form>
@endsection
