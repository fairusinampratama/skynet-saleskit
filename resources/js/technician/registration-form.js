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
    const ktpFramePlaceholder = document.getElementById('ktpFramePlaceholder');
    const canvas = document.getElementById('ktpCanvas');
    const preview = document.getElementById('ktpPreview');
    const processed = document.getElementById('processedKtp');
    const ocrFieldSources = document.getElementById('ocrFieldSources');
    const ktpCameraInput = document.getElementById('ktpCameraInput');
    const ktpUploadInput = document.getElementById('ktpUploadInput');
    const ktpScanStatus = document.getElementById('ktpScanStatus');
    const startCamera = document.getElementById('startCamera');
    const uploadKtp = document.getElementById('uploadKtp');
    const uploadKtpReady = document.getElementById('uploadKtpReady');
    const scanKtpText = document.getElementById('scanKtpText');
    const retakeKtpInline = document.getElementById('retakeKtpInline');
    const ktpActionsEmpty = document.querySelector('[data-ktp-actions-empty]');
    const ktpActionsReady = document.querySelector('[data-ktp-actions-ready]');
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
    let activeStep = 'ktp';
    let ktpState = processed.value.trim() !== '' ? 'photo_ready' : 'empty';
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

    const setButtonLabel = (button, label) => {
        const labelElement = button.querySelector('[data-button-label]');
        if (labelElement) {
            labelElement.textContent = label;
        }
    };

    const setKtpState = state => {
        ktpState = state;
        const hasProcessedPhoto = processed.value.trim() !== '';

        preview.classList.toggle('hidden', ! hasProcessedPhoto);
        ktpFramePlaceholder.classList.toggle('hidden', hasProcessedPhoto);
        scanKtpText.disabled = ! hasProcessedPhoto || state === 'ocr_loading';
        startCamera.disabled = state === 'ocr_loading';
        uploadKtp.disabled = state === 'ocr_loading';
        uploadKtpReady.disabled = state === 'ocr_loading';
        retakeKtpInline.disabled = state === 'ocr_loading';
        ktpActionsEmpty.classList.toggle('hidden', hasProcessedPhoto);
        ktpActionsReady.classList.toggle('hidden', ! hasProcessedPhoto);

        setButtonLabel(scanKtpText, state === 'ocr_loading' ? 'Membaca Teks...' : 'Baca Teks KTP');
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
        const hasKtp = existingKtpDocument || ktpCameraInput.files.length > 0 || ktpUploadInput.files.length > 0 || processed.value.trim() !== '';
        const hasGps = fieldValue('latitude') !== '' && fieldValue('longitude') !== '';
        const hasEvidencePhoto = existingEvidence || locationPhoto.files.length > 0;
        const customerComplete = ['name', 'nik', 'phone', 'package'].every(fieldValue);
        const addressComplete = ['area_id', 'installation_full_address'].every(fieldValue);
        const evidenceComplete = hasGps;
        const formComplete = filledRequired === requiredFields.length && hasKtp;

        requiredProgress.textContent = `${filledRequired}/${requiredFields.length}`;
        ocrSummary.textContent = ocrFilledFields.length > 0 ? `${ocrFilledFields.length} field` : (hasKtp ? 'Siap' : 'Menunggu');
        gpsSummary.textContent = hasGps ? 'Tertangkap' : 'Manual';
        evidenceSummary.textContent = hasEvidencePhoto ? 'Ada Foto' : 'Opsional';

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
        reviewChecklist.textContent = missing.length > 0 ? `Kurang: ${missing.join(', ')}.` : 'Semua data wajib teknisi siap direview.';
    };

    const clearProcessedKtp = () => {
        processed.value = '';
        preview.classList.add('hidden');
        preview.removeAttribute('src');
        setKtpState('empty');
        updateRegistrationState();
    };

    const commitProcessedKtp = (dataUrl, message = 'Foto siap. Pastikan data terlihat jelas sebelum membaca teks.') => {
        processed.value = dataUrl;
        preview.src = dataUrl;
        preview.classList.remove('hidden');
        setKtpStatus(message);
        setKtpState('photo_ready');
        updateRegistrationState();
    };

    const ktpPhotoReadyMessage = warnings => {
        if (warnings.length === 0) {
            return 'Foto siap. Pastikan data terlihat jelas sebelum membaca teks.';
        }

        return `Foto siap. ${warnings.join(', ')}. Tetap bisa digunakan atau foto ulang jika perlu.`;
    };

    const processImageSource = source => {
        const sourceWidth = source.naturalWidth || source.width;
        const sourceHeight = source.naturalHeight || source.height;
        const warnings = [];

        if (! sourceWidth || ! sourceHeight) {
            setKtpStatus('Foto KTP tidak menghasilkan gambar. Coba foto ulang.');

            return null;
        }

        if (sourceWidth < 900 || sourceHeight < 500) {
            warnings.push('resolusi kamera rendah');
        }

        const maxWidth = 1600;
        const scale = Math.min(1, maxWidth / sourceWidth);
        canvas.width = Math.round(sourceWidth * scale);
        canvas.height = Math.round(sourceHeight * scale);

        const context = canvas.getContext('2d');
        context.filter = 'contrast(1.06) brightness(1.03) saturate(0.94)';
        context.drawImage(source, 0, 0, canvas.width, canvas.height);

        if (blurScore(context, canvas.width, canvas.height) < 7) {
            warnings.push('foto mungkin buram');
        }

        return {
            dataUrl: canvas.toDataURL('image/jpeg', 0.88),
            warnings,
        };
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

    const processSelectedKtp = input => {
        const file = input.files[0];

        if (! file) {
            setKtpStatus('Pilih atau ambil foto KTP terlebih dulu.');
            return Promise.resolve(false);
        }

        setKtpStatus('Memproses foto KTP...');

        if (uploadedKtpUrl) {
            URL.revokeObjectURL(uploadedKtpUrl);
        }

        uploadedKtpUrl = URL.createObjectURL(file);

        const image = new Image();

        return new Promise(resolve => {
            image.onload = () => {
                const processedImage = processImageSource(image);

                if (! processedImage) {
                    input.value = '';
                    setKtpState(processed.value.trim() !== '' ? 'photo_ready' : 'empty');
                    updateRegistrationState();
                    resolve(false);
                    return;
                }

                commitProcessedKtp(processedImage.dataUrl, ktpPhotoReadyMessage(processedImage.warnings));
                resolve(true);
            };

            image.onerror = () => {
                clearProcessedKtp();
                input.value = '';
                setKtpStatus('Foto KTP yang diunggah tidak dapat dibaca. Coba gambar lain.');
                resolve(false);
            };

            image.src = uploadedKtpUrl;
        });
    };

    const scanProcessedKtp = async () => {
        if (! processed.value) {
            setKtpStatus('Ambil atau unggah foto KTP terlebih dulu.');
            return;
        }

        setKtpStatus('Membaca teks KTP...');
        setKtpState('ocr_loading');

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
                setKtpState('ocr_failed');
                return;
            }

            const parsed = result.parsed || {};
            const confidence = result.confidence || {};
            const fieldConfidence = confidence.fields || {};
            ocrSuggestions = {
                nik: parsed.nik,
                name: parsed.name,
                ktp_full_address: parsed.address,
            };

            const filled = fillBlankFieldsFromOcr(parsed, {
                nik: fieldConfidence.nik,
                name: fieldConfidence.name,
                address: fieldConfidence.address,
            });
            ocrFilledFields = filled;
            const status = statusLabels[confidence.status] || 'Perlu konfirmasi';

            if (filled.length > 0) {
                setKtpStatus(`${status}: pindai KTP mengisi ${filled.length} field berkeyakinan tinggi: ${filled.join(', ')}.`);
                setKtpState('ocr_success');
                updateRegistrationState();
                return;
            }

            setKtpStatus(result.error || `${status}: review saran OCR atau isi field secara manual.`);
            setKtpState(result.error ? 'ocr_failed' : 'ocr_success');
            updateRegistrationState();
        } catch (error) {
            setKtpStatus('OCR belum tersedia. Isi data KTP manual atau coba lagi nanti.');
            setKtpState('ocr_failed');
            updateRegistrationState();
        }
    };

    startCamera.addEventListener('click', () => ktpCameraInput.click());
    uploadKtp.addEventListener('click', () => ktpUploadInput.click());
    uploadKtpReady.addEventListener('click', () => ktpUploadInput.click());
    retakeKtpInline.addEventListener('click', () => ktpCameraInput.click());
    scanKtpText.addEventListener('click', scanProcessedKtp);

    ktpCameraInput.addEventListener('change', () => processSelectedKtp(ktpCameraInput));
    ktpUploadInput.addEventListener('change', () => processSelectedKtp(ktpUploadInput));

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

    setKtpState(ktpState);
    updateRegistrationState();
};

document.addEventListener('DOMContentLoaded', initTechnicianRegistrationForm);
