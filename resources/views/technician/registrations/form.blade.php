@extends('technician.layout', ['title' => $registration ? __('registration.title.edit') : __('registration.title.new')])

@php
    $hasEvidence = (bool) $registration?->evidence?->isNotEmpty();
    $hasKtpDocument = (bool) ($registration?->ktp_original_file_path || $registration?->ktp_processed_file_path);
@endphp

@section('content')
    <form
        id="registrationForm"
        class="grid gap-4"
        method="POST"
        enctype="multipart/form-data"
        action="{{ $registration ? route('technician.registrations.update', $registration) : route('technician.registrations.store') }}"
        data-registration-form
        data-scan-ktp-url="{{ route('technician.registrations.scan-ktp', [], false) }}"
        data-existing-evidence="{{ $hasEvidence ? '1' : '0' }}"
        data-existing-ktp-document="{{ $hasKtpDocument ? '1' : '0' }}"
    >
        @csrf
        @if ($registration)
            @method('PUT')
        @endif

        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold leading-tight">{{ $registration ? __('registration.title.edit') : __('registration.title.new') }}</h1>
                <p class="mt-1 text-sm text-slate-500">Lengkapi data pelanggan, KTP, alamat, GPS, dan bukti sebelum dikirim.</p>
            </div>
            @if ($registration)
                <x-tech.status-badge>{{ __('registration.status.'.$registration->status) }}</x-tech.status-badge>
            @endif
        </div>

        <x-tech.panel>
            <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                <x-tech.summary-tile label="Field wajib" value="0/0" value-id="requiredProgress" />
                <x-tech.summary-tile label="OCR KTP" value="Menunggu" value-id="ocrSummary" />
                <x-tech.summary-tile label="GPS" value="Manual" value-id="gpsSummary" />
                <x-tech.summary-tile label="Bukti" value="Diperlukan" value-id="evidenceSummary" />
            </div>
        </x-tech.panel>

        <x-tech.step-nav aria-label="Bagian registrasi">
            <x-tech.step-tab active data-step-target="customer">Pelanggan</x-tech.step-tab>
            <x-tech.step-tab data-step-target="ktp">KTP/OCR</x-tech.step-tab>
            <x-tech.step-tab data-step-target="address">Alamat</x-tech.step-tab>
            <x-tech.step-tab data-step-target="evidence">GPS & Bukti</x-tech.step-tab>
            <x-tech.step-tab data-step-target="review">Tinjau</x-tech.step-tab>
        </x-tech.step-nav>

        <x-tech.panel id="step-customer" class="registration-step scroll-mt-28" data-step="customer">
            <div class="mb-3 flex items-start justify-between gap-3">
                <div>
                    <div class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Langkah 1</div>
                    <h2 class="text-base font-extrabold">Pelanggan</h2>
                </div>
                <x-tech.status-badge variant="warn" data-step-status="customer">{{ __('ui.common.incomplete') }}</x-tech.status-badge>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                <x-tech.field label="Nama Pelanggan" name="name" :value="old('name', $registration?->name)" data-required-field />
                <x-tech.field label="NIK" name="nik" :value="old('nik', $registration?->nik)" inputmode="numeric" data-required-field />
                <x-tech.field label="Nomor Telepon" name="phone" :value="old('phone', $registration?->phone)" inputmode="tel" data-required-field />
                <x-tech.field label="Email" name="email" type="email" :value="old('email', $registration?->email)" />
                <x-tech.select label="Paket" name="package" data-required-field>
                    <option value="">Pilih paket</option>
                    @foreach (\App\Models\Registration::PACKAGES as $package)
                        <option value="{{ $package }}" @selected(old('package', $registration?->package) === $package)>{{ $package }}</option>
                    @endforeach
                </x-tech.select>
            </div>
        </x-tech.panel>

        <x-tech.panel id="step-ktp" class="registration-step scroll-mt-28" data-step="ktp">
            <div class="mb-3 flex items-start justify-between gap-3">
                <div>
                    <div class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Langkah 2</div>
                    <h2 class="text-base font-extrabold">Pindai KTP</h2>
                </div>
                <x-tech.status-badge variant="warn" data-step-status="ktp">{{ __('ui.common.needed') }}</x-tech.status-badge>
            </div>
            <div class="grid gap-3">
                <div class="ktp-frame">
                    <img id="ktpPreview" class="ktp-frame-preview" alt="Pratinjau foto KTP" hidden>
                    <div class="ktp-frame-placeholder">
                        <x-heroicon-o-identification class="h-10 w-10 text-slate-400" />
                        <div>
                            <p class="font-extrabold text-slate-100">Belum ada foto KTP</p>
                            <p class="mt-1 text-sm text-slate-400">Ambil atau unggah foto, periksa hasilnya, lalu baca teks KTP.</p>
                        </div>
                    </div>
                </div>
                <canvas id="ktpCanvas" hidden></canvas>
                <input id="processedKtp" name="processed_ktp_image" type="hidden">
                <input id="ocrFieldSources" name="ocr_field_sources" type="hidden">
                <div class="grid gap-2 sm:grid-cols-3">
                    <x-tech.button variant="secondary" type="button" id="startCamera" icon="camera" full>Ambil Foto KTP</x-tech.button>
                    <x-tech.button variant="secondary" type="button" id="uploadKtp" icon="arrow-up-tray" full>Unggah Foto</x-tech.button>
                    <x-tech.button variant="primary" type="button" id="scanKtpText" icon="document-magnifying-glass" full disabled>Baca Teks KTP</x-tech.button>
                </div>
                <p id="ktpScanStatus" class="text-sm text-slate-500" role="status">Ambil atau unggah foto KTP untuk mulai.</p>
                <input class="sr-only" name="ktp_image" type="file" id="ktpInput" accept="image/*" capture="environment">
                <div id="ktpCaptureOverlay" class="ktp-capture-overlay" aria-modal="true" role="dialog" aria-label="Ambil foto KTP" hidden>
                    <div class="ktp-capture-shell">
                        <div class="ktp-capture-header">
                            <button type="button" id="closeKtpCapture" class="ktp-capture-icon-btn" aria-label="Tutup kamera">
                                <x-heroicon-o-x-mark class="h-6 w-6" />
                            </button>
                            <span id="ktpCaptureTitle" class="text-sm font-extrabold text-white">Ambil Foto KTP</span>
                            <span class="h-11 w-11" aria-hidden="true"></span>
                        </div>
                        <div class="ktp-capture-stage">
                            <video id="camera" playsinline muted></video>
                            <img id="ktpCapturePreview" alt="Foto KTP yang diambil" hidden>
                            <div class="ktp-capture-guide">
                                <span>KTP penuh, lanskap, tidak buram</span>
                            </div>
                        </div>
                        <p id="ktpCaptureStatus" class="ktp-capture-status" role="status">Posisikan KTP di dalam bingkai.</p>
                        <div class="ktp-capture-actions">
                            <x-tech.button variant="secondary" type="button" id="retakeKtpPhoto" icon="arrow-path" full hidden>Foto Ulang</x-tech.button>
                            <x-tech.button variant="primary" type="button" id="captureKtpPhoto" icon="camera" full>Ambil</x-tech.button>
                            <x-tech.button variant="primary" type="button" id="useKtpPhoto" icon="check" full hidden>Gunakan Foto</x-tech.button>
                        </div>
                    </div>
                </div>
            </div>
        </x-tech.panel>

        <x-tech.panel id="step-address" class="registration-step scroll-mt-28" data-step="address">
            <div class="mb-3 flex items-start justify-between gap-3">
                <div>
                    <div class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Langkah 3</div>
                    <h2 class="text-base font-extrabold">Alamat dan Area</h2>
                </div>
                <x-tech.status-badge variant="warn" data-step-status="address">{{ __('ui.common.incomplete') }}</x-tech.status-badge>
            </div>
            <div class="grid gap-3">
                <x-tech.select label="Area" name="area_id" data-required-field>
                    <option value="">Pilih area</option>
                    @foreach ($areas as $area)
                        <option value="{{ $area->id }}" @selected((string) old('area_id', $registration?->area_id) === (string) $area->id)>
                            {{ $area->name }}
                        </option>
                    @endforeach
                </x-tech.select>
                <x-tech.textarea label="Alamat KTP" name="ktp_full_address">{{ old('ktp_full_address', $registration?->ktp_full_address) }}</x-tech.textarea>
                <x-tech.textarea label="Alamat Instalasi" name="installation_full_address" data-required-field>{{ old('installation_full_address', $registration?->installation_full_address) }}</x-tech.textarea>
            </div>
            <div class="mt-3">
                <x-tech.button variant="ghost" type="button" id="copyKtpAddress" icon="clipboard-document">{{ __('ui.actions.copy_ktp_address') }}</x-tech.button>
            </div>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                <x-tech.field label="Provinsi" name="province" :value="old('province', $registration?->province)" />
                <x-tech.field label="Kota / Kabupaten" name="city" :value="old('city', $registration?->city)" />
                <x-tech.field label="Kecamatan" name="district" :value="old('district', $registration?->district)" />
                <x-tech.field label="Desa / Kelurahan" name="village" :value="old('village', $registration?->village)" />
                <x-tech.field label="RT" name="rt" :value="old('rt', $registration?->rt)" />
                <x-tech.field label="RW" name="rw" :value="old('rw', $registration?->rw)" />
                <x-tech.field label="Kode Pos" name="postal_code" :value="old('postal_code', $registration?->postal_code)" />
            </div>
        </x-tech.panel>

        <x-tech.panel id="step-evidence" class="registration-step scroll-mt-28" data-step="evidence">
            <div class="mb-3 flex items-start justify-between gap-3">
                <div>
                    <div class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Langkah 4</div>
                    <h2 class="text-base font-extrabold">GPS dan Bukti</h2>
                </div>
                <x-tech.status-badge variant="warn" data-step-status="evidence">{{ __('ui.common.incomplete') }}</x-tech.status-badge>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                <x-tech.field label="Latitude" name="latitude" id="latitude" :value="old('latitude', $registration?->latitude)" data-required-field />
                <x-tech.field label="Longitude" name="longitude" id="longitude" :value="old('longitude', $registration?->longitude)" data-required-field />
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <x-tech.button variant="secondary" type="button" id="captureGps" icon="map-pin">{{ __('ui.actions.use_current_gps') }}</x-tech.button>
                <span id="gpsStatus" class="text-sm text-slate-500" role="status">Gunakan GPS atau isi koordinat secara manual.</span>
            </div>
            <div class="mt-3 grid gap-3">
                <x-tech.field label="Foto Rumah / Lokasi" name="location_photo" type="file" id="locationPhoto" accept="image/*" capture="environment" />
                <x-tech.textarea label="Catatan Teknisi" name="technician_notes">{{ old('technician_notes', $registration?->technician_notes) }}</x-tech.textarea>
            </div>
        </x-tech.panel>

        <x-tech.panel id="step-review" class="registration-step scroll-mt-28" data-step="review">
            <div class="mb-3 flex items-start justify-between gap-3">
                <div>
                    <div class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Langkah 5</div>
                    <h2 class="text-base font-extrabold">Tinjau dan Kirim</h2>
                </div>
                <x-tech.status-badge variant="warn" id="reviewStatus">Tinjau</x-tech.status-badge>
            </div>
            <div class="grid gap-2">
                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <strong id="reviewCustomer" class="block text-slate-950">Pelanggan belum diisi</strong>
                    <span id="reviewContact" class="text-sm text-slate-500">Telepon dan area akan tampil di sini.</span>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <strong class="block text-slate-950">Checklist registrasi</strong>
                    <span id="reviewChecklist" class="text-sm text-slate-500">Lengkapi field wajib sebelum mengirim.</span>
                </div>
            </div>
        </x-tech.panel>

        <div class="hidden flex-wrap gap-2 md:flex">
            <x-tech.button variant="secondary" type="submit" name="action" value="draft" icon="archive-box">{{ __('ui.actions.save_draft') }}</x-tech.button>
            <x-tech.button type="submit" name="action" value="submit" icon="paper-airplane">{{ __('ui.actions.submit_review') }}</x-tech.button>
        </div>

        <x-tech.mobile-action-bar>
            <x-tech.button variant="secondary" type="submit" name="action" value="draft" icon="archive-box" full>{{ __('ui.actions.save_draft') }}</x-tech.button>
            <x-tech.button type="button" id="mobilePrimaryAction" full>Lanjut</x-tech.button>
        </x-tech.mobile-action-bar>
    </form>
@endsection
