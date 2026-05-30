const badgeClasses = {
    ok: ['bg-emerald-50', 'text-emerald-700'],
    warn: ['bg-amber-50', 'text-amber-800'],
};

const tabClasses = {
    active: ['bg-slate-900', 'text-white', 'shadow-sm'],
    inactive: ['text-slate-500', 'hover:bg-slate-100', 'hover:text-slate-900'],
};

const setClassGroup = (element, groups, activeGroup) => {
    Object.values(groups).flat().forEach(className => element.classList.remove(className));
    element.classList.add(...groups[activeGroup]);
};

const initTechnicianRegistrationForm = () => {
    const form = document.querySelector('[data-registration-form]');

    if (! form) {
        return;
    }

    const scanKtpUrl = form.dataset.scanKtpUrl;
    const existingEvidence = form.dataset.existingEvidence === '1';
    const existingKtpDocument = form.dataset.existingKtpDocument === '1';
    const csrfToken = form.querySelector('input[name="_token"]').value;
    const video = document.getElementById('camera');
    const ktpFrame = video.closest('.ktp-frame');
    const canvas = document.getElementById('ktpCanvas');
    const preview = document.getElementById('ktpPreview');
    const processed = document.getElementById('processedKtp');
    const ocrFieldSources = document.getElementById('ocrFieldSources');
    const ktpInput = document.getElementById('ktpInput');
    const ktpScanStatus = document.getElementById('ktpScanStatus');
    const startCamera = document.getElementById('startCamera');
    const captureKtp = document.getElementById('captureKtp');
    const gpsStatus = document.getElementById('gpsStatus');
    const locationPhoto = document.getElementById('locationPhoto');
    const stepTabs = [...document.querySelectorAll('[data-step-target]')];
    const requiredFields = [...document.querySelectorAll('[data-required-field]')];
    const mobilePrimaryAction = document.getElementById('mobilePrimaryAction');
    const reviewStatus = document.getElementById('reviewStatus');
    const reviewCustomer = document.getElementById('reviewCustomer');
    const reviewContact = document.getElementById('reviewContact');
    const reviewChecklist = document.getElementById('reviewChecklist');
    const requiredProgress = document.getElementById('requiredProgress');
    const ocrSummary = document.getElementById('ocrSummary');
    const gpsSummary = document.getElementById('gpsSummary');
    const evidenceSummary = document.getElementById('evidenceSummary');
    let uploadedKtpUrl = null;
    let activeStep = 'customer';
    let ocrFilledFields = [];
    let ocrSuggestions = {};
    let fieldSources = {};

    const setKtpStatus = message => {
        ktpScanStatus.textContent = message;
    };

    const statusLabels = {
        good_scan: 'Pindai bagus',
        needs_confirmation: 'Perlu konfirmasi',
        retake_recommended: 'Disarankan foto ulang',
        manual_entry_required: 'Perlu input manual',
    };

    const setStartCameraLabel = label => {
        const labelElement = startCamera.querySelector('[data-button-label]');
        if (labelElement) {
            labelElement.textContent = label;
        }
    };

    const setUploadCameraMode = () => {
        ktpFrame.classList.add('is-upload-mode');
        setStartCameraLabel('Ambil/Unggah Foto KTP');
        setKtpStatus('Mode HTTP lokal memakai unggahan kamera ponsel. Panduan kamera langsung hanya tersedia saat aplikasi berjalan melalui HTTPS.');
    };

    const fieldValue = name => (form.elements[name]?.value || '').trim();

    const setFieldSource = (name, source) => {
        fieldSources[name] = source;
        ocrFieldSources.value = JSON.stringify(fieldSources);
    };

    const setStatus = (element, complete, completeText = 'Lengkap', incompleteText = 'Belum lengkap') => {
        element.textContent = complete ? completeText : incompleteText;
        setClassGroup(element, badgeClasses, complete ? 'ok' : 'warn');
    };

    const updateMobileAction = () => {
        mobilePrimaryAction.textContent = activeStep === 'review' ? 'Kirim' : 'Lanjut';
    };

    const showStep = step => {
        activeStep = step;

        stepTabs.forEach(tab => {
            setClassGroup(tab, tabClasses, tab.dataset.stepTarget === step ? 'active' : 'inactive');
        });

        document.getElementById(`step-${step}`)?.scrollIntoView({ block: 'start', behavior: 'smooth' });
        updateMobileAction();
    };

    const currentStepIndex = () => stepTabs.findIndex(tab => tab.dataset.stepTarget === activeStep);

    const submitForReview = () => {
        const submitter = document.createElement('button');
        submitter.type = 'submit';
        submitter.name = 'action';
        submitter.value = 'submit';
        submitter.hidden = true;
        form.appendChild(submitter);
        submitter.click();
        submitter.remove();
    };

    const goToNextStep = () => {
        const nextTab = stepTabs[currentStepIndex() + 1];

        if (nextTab) {
            showStep(nextTab.dataset.stepTarget);
            return;
        }

        submitForReview();
    };

    const suggestionElementFor = field => {
        const wrapper = field.closest('label');

        if (! wrapper) {
            return null;
        }

        let element = wrapper.querySelector('[data-ocr-suggestion]');

        if (! element) {
            element = document.createElement('button');
            element.type = 'button';
            element.className = 'text-left text-xs font-semibold text-amber-800';
            element.dataset.ocrSuggestion = '1';
            wrapper.appendChild(element);
        }

        return element;
    };

    const setOcrSuggestion = (name, value, confidence) => {
        const field = form.elements[name];

        if (! field || ! value) {
            return;
        }

        const element = suggestionElementFor(field);

        if (! element) {
            return;
        }

        element.textContent = `OCR ${confidence}: ${value}`;
        element.hidden = false;
        element.onclick = () => {
            field.value = value;
            setFieldSource(name, `ocr_${confidence}_accepted`);
            element.hidden = true;
            updateRegistrationState();
        };
    };

    const fillBlankField = (name, value, confidence = 'low') => {
        if (! value) {
            return null;
        }

        const field = form.elements[name];

        if (! field || field.value.trim() !== '') {
            if (field) {
                setOcrSuggestion(name, value, confidence);
            }

            return null;
        }

        field.value = value;
        setFieldSource(name, `ocr_${confidence}_autofilled`);

        return name;
    };

    const fillBlankFieldsFromOcr = (parsed, confidence = {}) => [
        fillBlankField('nik', parsed.nik, confidence.nik),
        fillBlankField('name', parsed.name, confidence.name),
        fillBlankField('ktp_full_address', parsed.address, confidence.address),
        fillBlankField('province', parsed.province, confidence.province),
        fillBlankField('city', parsed.city, confidence.city),
        fillBlankField('rt', parsed.rt, confidence.rt),
        fillBlankField('rw', parsed.rw, confidence.rw),
        fillBlankField('village', parsed.village, confidence.village),
        fillBlankField('district', parsed.district, confidence.district),
    ].filter(Boolean);

    const markManualEdits = event => {
        const field = event.target;

        if (! field?.name || field.dataset.ocrSuggestion) {
            return;
        }

        if (ocrSuggestions[field.name] && field.value.trim() !== '') {
            setFieldSource(field.name, fieldSources[field.name] || 'manual_or_corrected');
        }
    };

    const updateRegistrationState = () => {
        const filledRequired = requiredFields.filter(field => field.value.trim() !== '').length;
        const hasKtp = existingKtpDocument || ktpInput.files.length > 0 || processed.value.trim() !== '';
        const hasGps = fieldValue('latitude') !== '' && fieldValue('longitude') !== '';
        const hasEvidencePhoto = existingEvidence || locationPhoto.files.length > 0;
        const customerComplete = ['name', 'nik', 'phone', 'package'].every(fieldValue);
        const addressComplete = ['area_id', 'ktp_full_address', 'installation_full_address', 'province', 'city', 'district', 'village'].every(fieldValue);
        const evidenceComplete = hasGps && hasEvidencePhoto;
        const formComplete = filledRequired === requiredFields.length && hasKtp && hasEvidencePhoto;

        requiredProgress.textContent = `${filledRequired}/${requiredFields.length}`;
        ocrSummary.textContent = ocrFilledFields.length > 0 ? `${ocrFilledFields.length} field` : (hasKtp ? 'Siap' : 'Menunggu');
        gpsSummary.textContent = hasGps ? 'Tertangkap' : 'Manual';
        evidenceSummary.textContent = hasEvidencePhoto ? 'Siap' : 'Diperlukan';

        setStatus(document.querySelector('[data-step-status="customer"]'), customerComplete);
        setStatus(document.querySelector('[data-step-status="ktp"]'), hasKtp, 'Siap', 'Diperlukan');
        setStatus(document.querySelector('[data-step-status="address"]'), addressComplete);
        setStatus(document.querySelector('[data-step-status="evidence"]'), evidenceComplete);
        setStatus(reviewStatus, formComplete, 'Siap', 'Tinjau');

        reviewCustomer.textContent = fieldValue('name') || 'Pelanggan belum diisi';
        reviewContact.textContent = [
            fieldValue('phone') || 'Telepon belum ada',
            fieldValue('package') || 'Paket belum ada',
            form.elements.area_id?.selectedOptions[0]?.textContent.trim() || 'Area belum dipilih',
        ].join(' · ');

        const missing = [];
        if (filledRequired !== requiredFields.length) missing.push(`${requiredFields.length - filledRequired} field wajib`);
        if (! hasKtp) missing.push('foto KTP');
        if (! hasEvidencePhoto) missing.push('foto lokasi');
        reviewChecklist.textContent = missing.length > 0 ? `Kurang: ${missing.join(', ')}.` : 'Semua data wajib teknisi siap direview.';
    };

    const processImageSource = source => {
        const ratio = 1.58;
        let cropWidth = source.width || source.videoWidth;
        let cropHeight = cropWidth / ratio;
        const sourceHeight = source.height || source.videoHeight;
        const sourceWidth = source.width || source.videoWidth;
        const sourceRatio = sourceWidth / Math.max(sourceHeight, 1);

        if (sourceWidth < 900 || sourceHeight < 500) {
            processed.value = '';
            preview.hidden = true;
            setKtpStatus('Disarankan foto ulang: gambar KTP terlalu kecil.');
            updateRegistrationState();

            return false;
        }

        if (sourceRatio < 1.2) {
            processed.value = '';
            preview.hidden = true;
            setKtpStatus('Disarankan foto ulang: pegang KTP dalam orientasi lanskap.');
            updateRegistrationState();

            return false;
        }

        if (cropHeight > sourceHeight) {
            cropHeight = sourceHeight;
            cropWidth = cropHeight * ratio;
        }

        const cropX = (sourceWidth - cropWidth) / 2;
        const cropY = (sourceHeight - cropHeight) / 2;

        canvas.width = 1280;
        canvas.height = Math.round(1280 / ratio);

        const context = canvas.getContext('2d');
        context.filter = 'contrast(1.08) brightness(1.04) saturate(0.92)';
        context.drawImage(source, cropX, cropY, cropWidth, cropHeight, 0, 0, canvas.width, canvas.height);

        if (blurScore(context, canvas.width, canvas.height) < 7) {
            processed.value = '';
            preview.hidden = true;
            setKtpStatus('Disarankan foto ulang: gambar KTP terlihat buram.');
            updateRegistrationState();

            return false;
        }

        processed.value = canvas.toDataURL('image/jpeg', 0.88);
        preview.src = processed.value;
        preview.hidden = false;
        setKtpStatus('Pratinjau pindai KTP siap.');
        updateRegistrationState();

        return true;
    };

    const blurScore = (context, width, height) => {
        const sampleWidth = 160;
        const sampleHeight = Math.round(sampleWidth * (height / width));
        const sample = document.createElement('canvas');
        sample.width = sampleWidth;
        sample.height = sampleHeight;
        const sampleContext = sample.getContext('2d');
        sampleContext.drawImage(canvas, 0, 0, sampleWidth, sampleHeight);
        const data = sampleContext.getImageData(0, 0, sampleWidth, sampleHeight).data;
        let total = 0;
        let totalSquared = 0;
        let count = 0;

        for (let y = 1; y < sampleHeight - 1; y += 1) {
            for (let x = 1; x < sampleWidth - 1; x += 1) {
                const index = (y * sampleWidth + x) * 4;
                const center = data[index];
                const left = data[index - 4];
                const right = data[index + 4];
                const top = data[index - sampleWidth * 4];
                const bottom = data[index + sampleWidth * 4];
                const laplacian = Math.abs((4 * center) - left - right - top - bottom);
                total += laplacian;
                totalSquared += laplacian * laplacian;
                count += 1;
            }
        }

        const mean = total / Math.max(count, 1);

        return (totalSquared / Math.max(count, 1)) - (mean * mean);
    };

    const processUploadedKtp = () => {
        const file = ktpInput.files[0];

        if (! file) {
            setKtpStatus('Pilih foto KTP dulu, atau buka kamera.');
            return Promise.resolve(false);
        }

        setKtpStatus('Memproses foto KTP yang diunggah...');

        if (uploadedKtpUrl) {
            URL.revokeObjectURL(uploadedKtpUrl);
        }

        uploadedKtpUrl = URL.createObjectURL(file);

        const image = new Image();

        return new Promise(resolve => {
            image.onload = () => {
                resolve(processImageSource(image));
            };

            image.onerror = () => {
                processed.value = '';
                preview.hidden = true;
                preview.removeAttribute('src');
                setKtpStatus('Foto KTP yang diunggah tidak dapat dibaca. Coba gambar lain.');
                updateRegistrationState();
                resolve(false);
            };

            image.src = uploadedKtpUrl;
        });
    };

    const processCameraKtp = () => {
        if (! video.videoWidth) {
            return processUploadedKtp();
        }

        return Promise.resolve(processImageSource(video));
    };

    const scanProcessedKtp = async () => {
        if (! processed.value) {
            setKtpStatus('Pindai gambar KTP terlebih dulu.');
            return;
        }

        setKtpStatus('Membaca teks KTP...');

        try {
            const response = await fetch(scanKtpUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ processed_ktp_image: processed.value }),
            });

            const result = await response.json();

            if (! response.ok) {
                setKtpStatus(result.message || 'OCR KTP gagal. Isi field secara manual.');
                return;
            }

            const parsed = result.parsed || {};
            const confidence = result.confidence || {};
            const fieldConfidence = confidence.fields || {};
            ocrSuggestions = {
                nik: parsed.nik,
                name: parsed.name,
                ktp_full_address: parsed.address,
                province: parsed.province,
                city: parsed.city,
                rt: parsed.rt,
                rw: parsed.rw,
                village: parsed.village,
                district: parsed.district,
            };

            const filled = fillBlankFieldsFromOcr(parsed, {
                nik: fieldConfidence.nik,
                name: fieldConfidence.name,
                address: fieldConfidence.address,
                province: fieldConfidence.province,
                city: fieldConfidence.city,
                rt: fieldConfidence.rt,
                rw: fieldConfidence.rw,
                village: fieldConfidence.village,
                district: fieldConfidence.district,
            });
            ocrFilledFields = filled;
            const status = statusLabels[confidence.status] || 'Perlu konfirmasi';

            if (filled.length > 0) {
                setKtpStatus(`${status}: pindai KTP mengisi ${filled.length} field berkeyakinan tinggi: ${filled.join(', ')}.`);
                updateRegistrationState();
                return;
            }

            setKtpStatus(result.error || `${status}: review saran OCR atau isi field secara manual.`);
            updateRegistrationState();
        } catch (error) {
            setKtpStatus('OCR KTP tidak dapat diakses. Isi field secara manual.');
            updateRegistrationState();
        }
    };

    if (! window.isSecureContext || ! navigator.mediaDevices?.getUserMedia) {
        setUploadCameraMode();
    }

    startCamera.addEventListener('click', async () => {
        if (! window.isSecureContext) {
            ktpFrame.classList.remove('is-active');
            setKtpStatus('Pratinjau kamera langsung membutuhkan HTTPS di ponsel. Membuka unggahan kamera ponsel sebagai gantinya.');
            ktpInput.click();
            return;
        }

        if (! navigator.mediaDevices?.getUserMedia) {
            ktpFrame.classList.remove('is-active');
            setKtpStatus('Browser ini tidak dapat menampilkan pratinjau kamera langsung. Membuka unggahan kamera ponsel sebagai gantinya.');
            ktpInput.click();
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            video.srcObject = stream;
            await video.play();
            ktpFrame.classList.add('is-active');
            setKtpStatus('Kamera siap. Posisikan KTP di dalam bingkai, lalu pindai.');
        } catch (error) {
            ktpFrame.classList.remove('is-active');
            if (error.name === 'NotAllowedError') {
                setKtpStatus('Izin kamera ditolak. Izinkan akses kamera di browser, atau unggah foto KTP.');
                return;
            }

            if (error.name === 'NotFoundError') {
                setKtpStatus('Kamera tidak ditemukan di perangkat ini. Unggah foto KTP sebagai gantinya.');
                return;
            }

            setKtpStatus('Kamera tidak dapat dibuka. Unggah foto KTP sebagai gantinya.');
        }
    });

    captureKtp.addEventListener('click', async () => {
        captureKtp.disabled = true;

        try {
            const scanned = await processCameraKtp();

            if (scanned) {
                await scanProcessedKtp();
            }
        } finally {
            captureKtp.disabled = false;
        }
    });

    ktpInput.addEventListener('change', processUploadedKtp);

    stepTabs.forEach(tab => {
        tab.addEventListener('click', () => showStep(tab.dataset.stepTarget));
    });

    mobilePrimaryAction.addEventListener('click', goToNextStep);

    form.addEventListener('input', event => {
        markManualEdits(event);
        updateRegistrationState();
    });
    form.addEventListener('change', updateRegistrationState);

    document.getElementById('copyKtpAddress').addEventListener('click', () => {
        const ktpAddress = form.elements.ktp_full_address.value.trim();

        if (! ktpAddress) {
            return;
        }

        form.elements.installation_full_address.value = ktpAddress;
        updateRegistrationState();
    });

    document.getElementById('captureGps').addEventListener('click', () => {
        gpsStatus.textContent = 'Mengambil GPS saat ini...';

        navigator.geolocation.getCurrentPosition(position => {
            document.getElementById('latitude').value = position.coords.latitude.toFixed(8);
            document.getElementById('longitude').value = position.coords.longitude.toFixed(8);
            gpsStatus.textContent = 'GPS berhasil diambil. Periksa koordinat sebelum mengirim.';
            updateRegistrationState();
        }, () => {
            gpsStatus.textContent = 'Izin GPS ditolak. Isi latitude dan longitude secara manual.';
            updateRegistrationState();
        }, { enableHighAccuracy: true, timeout: 12000 });
    });

    updateRegistrationState();
};

document.addEventListener('DOMContentLoaded', initTechnicianRegistrationForm);
