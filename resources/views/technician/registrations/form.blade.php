@extends('technician.layout', ['title' => $registration ? 'Edit Registration' : 'New Registration'])

@php
    $customer = $registration?->customer;
    $ktpAddress = $customer?->addresses?->firstWhere('address_type', 'ktp');
    $installationAddress = $customer?->addresses?->firstWhere('address_type', 'installation');
@endphp

@section('content')
    <form method="POST" enctype="multipart/form-data" action="{{ $registration ? route('technician.registrations.update', $registration) : route('technician.registrations.store') }}">
        @csrf
        @if ($registration)
            @method('PUT')
        @endif

        <div class="panel">
            <h1 class="section-title">{{ $registration ? 'Edit Registration' : 'New Registration' }}</h1>
            <div class="grid two">
                <label>
                    Customer Name
                    <input name="name" value="{{ old('name', $customer?->name) }}">
                </label>
                <label>
                    NIK
                    <input name="nik" value="{{ old('nik', $customer?->nik) }}" inputmode="numeric">
                </label>
                <label>
                    Phone
                    <input name="phone" value="{{ old('phone', $customer?->phone) }}" inputmode="tel">
                </label>
                <label>
                    Email
                    <input name="email" value="{{ old('email', $customer?->email) }}" type="email">
                </label>
            </div>
        </div>

        <div class="panel">
            <h2 class="section-title">KTP Scan</h2>
            <div class="camera">
                <div class="frame">
                    <video id="camera" playsinline muted></video>
                </div>
                <canvas id="ktpCanvas" hidden></canvas>
                <img id="ktpPreview" class="preview" alt="">
                <input id="processedKtp" name="processed_ktp_image" type="hidden">
                <div class="button-row">
                    <button class="btn secondary" type="button" id="startCamera">Open Camera</button>
                    <button class="btn secondary" type="button" id="captureKtp">Scan KTP</button>
                </div>
                <label>
                    Upload KTP Photo
                    <input id="ktpInput" name="ktp_image" type="file" accept="image/*" capture="environment">
                </label>
                <p class="muted">The app keeps the original photo and creates a processed image for OCR/review. Auto-crop uses the KTP frame area with manual file fallback.</p>
            </div>
        </div>

        <div class="panel">
            <h2 class="section-title">Address and Area</h2>
            <div class="grid">
                <label>
                    Area
                    <select name="area_id">
                        <option value="">Select area</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}" @selected((string) old('area_id', $registration?->area_id) === (string) $area->id)>
                                {{ $area->name }} ({{ $area->code }})
                            </option>
                        @endforeach
                    </select>
                </label>
                <label>
                    KTP Address
                    <textarea name="ktp_full_address">{{ old('ktp_full_address', $ktpAddress?->full_address) }}</textarea>
                </label>
                <label>
                    Installation Address
                    <textarea name="installation_full_address">{{ old('installation_full_address', $installationAddress?->full_address) }}</textarea>
                </label>
            </div>
            <div class="grid two" style="margin-top:12px;">
                <label>Province <input name="province" value="{{ old('province', $installationAddress?->province ?: $customer?->province) }}"></label>
                <label>City / Regency <input name="city" value="{{ old('city', $installationAddress?->city ?: $customer?->city) }}"></label>
                <label>District <input name="district" value="{{ old('district', $installationAddress?->district ?: $customer?->district) }}"></label>
                <label>Village <input name="village" value="{{ old('village', $installationAddress?->village ?: $customer?->village) }}"></label>
                <label>RT <input name="rt" value="{{ old('rt', $installationAddress?->rt ?: $customer?->rt) }}"></label>
                <label>RW <input name="rw" value="{{ old('rw', $installationAddress?->rw ?: $customer?->rw) }}"></label>
                <label>Postal Code <input name="postal_code" value="{{ old('postal_code', $installationAddress?->postal_code ?: $customer?->zip_code) }}"></label>
            </div>
        </div>

        <div class="panel">
            <h2 class="section-title">GPS and Evidence</h2>
            <div class="grid two">
                <label>Latitude <input id="latitude" name="latitude" value="{{ old('latitude', $installationAddress?->latitude ?: $customer?->latitude) }}"></label>
                <label>Longitude <input id="longitude" name="longitude" value="{{ old('longitude', $installationAddress?->longitude ?: $customer?->longitude) }}"></label>
            </div>
            <div class="button-row" style="margin:12px 0;">
                <button class="btn secondary" type="button" id="captureGps">Use Current GPS</button>
            </div>
            <label>
                House / Location Photo
                <input name="location_photo" type="file" accept="image/*" capture="environment">
            </label>
            <label style="margin-top:12px;">
                Technician Notes
                <textarea name="technician_notes">{{ old('technician_notes', $registration?->technician_notes) }}</textarea>
            </label>
        </div>

        <div class="button-row">
            <button class="btn secondary" name="action" value="draft" type="submit">Save Draft</button>
            <button class="btn primary" name="action" value="submit" type="submit">Submit for Review</button>
        </div>
    </form>

    <script>
        const video = document.getElementById('camera');
        const canvas = document.getElementById('ktpCanvas');
        const preview = document.getElementById('ktpPreview');
        const processed = document.getElementById('processedKtp');
        let stream = null;

        document.getElementById('startCamera').addEventListener('click', async () => {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            video.srcObject = stream;
            await video.play();
        });

        document.getElementById('captureKtp').addEventListener('click', () => {
            if (! video.videoWidth) return;
            const cropWidth = video.videoWidth * 0.8;
            const cropHeight = cropWidth / 1.58;
            const cropX = (video.videoWidth - cropWidth) / 2;
            const cropY = (video.videoHeight - cropHeight) / 2;
            canvas.width = 1280;
            canvas.height = Math.round(1280 / 1.58);
            const context = canvas.getContext('2d');
            context.filter = 'contrast(1.08) brightness(1.04) saturate(0.92)';
            context.drawImage(video, cropX, cropY, cropWidth, cropHeight, 0, 0, canvas.width, canvas.height);
            processed.value = canvas.toDataURL('image/jpeg', 0.88);
            preview.src = processed.value;
        });

        document.getElementById('ktpInput').addEventListener('change', event => {
            const file = event.target.files[0];
            if (! file) return;
            const image = new Image();
            image.onload = () => {
                const ratio = 1.58;
                let cropWidth = image.width;
                let cropHeight = cropWidth / ratio;
                if (cropHeight > image.height) {
                    cropHeight = image.height;
                    cropWidth = cropHeight * ratio;
                }
                const cropX = (image.width - cropWidth) / 2;
                const cropY = (image.height - cropHeight) / 2;
                canvas.width = 1280;
                canvas.height = Math.round(1280 / ratio);
                const context = canvas.getContext('2d');
                context.filter = 'contrast(1.08) brightness(1.04) saturate(0.92)';
                context.drawImage(image, cropX, cropY, cropWidth, cropHeight, 0, 0, canvas.width, canvas.height);
                processed.value = canvas.toDataURL('image/jpeg', 0.88);
                preview.src = processed.value;
            };
            image.src = URL.createObjectURL(file);
        });

        document.getElementById('captureGps').addEventListener('click', () => {
            navigator.geolocation.getCurrentPosition(position => {
                document.getElementById('latitude').value = position.coords.latitude.toFixed(8);
                document.getElementById('longitude').value = position.coords.longitude.toFixed(8);
            }, () => {
                alert('GPS permission denied. Enter latitude and longitude manually.');
            }, { enableHighAccuracy: true, timeout: 12000 });
        });
    </script>
@endsection
