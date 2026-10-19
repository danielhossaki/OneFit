document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-face-capture]');
    if (!root) return;

    const t = window.ofT || (text => text);
    const apiUrl = root.dataset.faceApi;
    const csrfToken = root.dataset.faceCsrf || '';
    const list = document.getElementById('boFacePeople');
    const search = document.getElementById('boFaceSearch');
    const selectedName = document.getElementById('boFaceSelectedName');
    const selectedId = document.getElementById('boFaceSelectedId');
    const faceStatus = document.getElementById('boFaceStatus');
    const confirmation = document.getElementById('boFaceConfirmation');
    const feedback = document.getElementById('boFaceFeedback');
    const video = document.getElementById('boFaceVideo');
    const preview = document.getElementById('boFacePreview');
    const canvas = document.getElementById('boFaceCanvas');
    const placeholder = document.getElementById('boFacePlaceholder');
    const startButton = document.getElementById('boFaceStart');
    const captureButton = document.getElementById('boFaceCapture');
    const stopButton = document.getElementById('boFaceStop');
    const retakeButton = document.getElementById('boFaceRetake');
    const cancelButton = document.getElementById('boFaceCancel');
    const saveButton = document.getElementById('boFaceSave');

    let people = [];
    let selected = null;
    let stream = null;
    let cameraRequestId = 0;
    let capturedImage = null;
    let previewUrl = '';
    let saving = false;
    let listRequestId = 0;
    let listController = null;
    let searchTimer = null;

    const setFeedback = (message, kind = '') => {
        feedback.textContent = message;
        feedback.classList.toggle('text-danger', kind === 'error');
        feedback.classList.toggle('text-success', kind === 'success');
        feedback.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    };

    const releaseCamera = () => {
        cameraRequestId++;
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        video.srcObject = null;
        video.hidden = true;
        captureButton.disabled = true;
        stopButton.hidden = true;
    };

    const clearPreview = () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = '';
        preview.removeAttribute('src');
        preview.hidden = true;
        capturedImage = null;
        confirmation.textContent = '';
        confirmation.hidden = true;
        retakeButton.hidden = true;
        saveButton.hidden = true;
        saveButton.disabled = true;
    };

    const setFacialStatus = registered => {
        faceStatus.textContent = registered ? t('Cadastrado') : t('Pendente');
        faceStatus.className = registered ? 'badge text-bg-success' : 'badge text-bg-secondary';
    };

    const renderPeople = () => {
        if (!list) return;
        list.replaceChildren();

        if (!people.length) {
            const empty = document.createElement('p');
            empty.className = 'bo-face-muted';
            empty.textContent = search.value.trim()
                ? t('Nenhuma pessoa corresponde à pesquisa.')
                : t('Nenhum usuário ativo encontrado.');
            list.append(empty);
            return;
        }

        const roleLabels = {
            aluno: t('Aluno'),
            profissional: t('Profissional'),
            admin: t('Administrador'),
        };
        people.forEach(person => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'bo-face-person';
            button.disabled = saving;
            button.setAttribute('aria-pressed', String(selected?.id === person.id));
            if (selected?.id === person.id) button.classList.add('is-selected');

            const info = document.createElement('span');
            const name = document.createElement('span');
            name.className = 'bo-face-person-name';
            name.textContent = person.nome;
            const meta = document.createElement('span');
            meta.className = 'bo-face-person-meta';
            meta.textContent = `${roleLabels[person.tipo_usuario] || t('Usuário')} · ID ${person.id}`;
            info.append(name, document.createElement('br'), meta);

            const state = document.createElement('span');
            state.className = 'bo-face-person-state';
            state.textContent = person.foto_cadastrada ? t('Cadastrado') : t('Pendente');
            const action = document.createElement('span');
            action.className = 'bo-face-person-action';
            action.textContent = selected?.id === person.id ? t('Selecionado') : t('Selecionar');
            button.append(info, state, action);
            button.addEventListener('click', () => selectPerson(person));
            list.append(button);
        });
    };

    const resetCaptureStep = message => {
        releaseCamera();
        clearPreview();
        placeholder.hidden = false;
        startButton.hidden = false;
        startButton.disabled = !selected || saving;
        captureButton.hidden = false;
        captureButton.disabled = true;
        retakeButton.hidden = true;
        cancelButton.hidden = !selected;
        saveButton.hidden = true;
        setFeedback(message || t('Câmera desligada.'));
    };

    const updateSelectedPerson = () => {
        if (!selected) {
            selectedName.textContent = t('Ninguém selecionado');
            selectedId.textContent = '';
            setFacialStatus(false);
            startButton.disabled = true;
            placeholder.querySelector('span').textContent = t('Selecione uma pessoa para habilitar a câmera.');
            return;
        }

        const roleLabels = {
            aluno: t('Aluno'),
            profissional: t('Profissional'),
            admin: t('Administrador'),
        };
        selectedName.textContent = selected.nome;
        selectedId.textContent = `${roleLabels[selected.tipo_usuario] || t('Usuário')} · ID #${selected.id}`;
        setFacialStatus(selected.foto_cadastrada);
        placeholder.querySelector('span').textContent = t('Posicione a pessoa diante da câmera e capture a foto.');
        startButton.disabled = saving;
    };

    function selectPerson(person) {
        if (saving) return;
        selected = person;
        resetCaptureStep(t('Câmera desligada.'));
        updateSelectedPerson();
        renderPeople();
    }

    const showListError = message => {
        list.replaceChildren();
        const error = document.createElement('p');
        error.className = 'bo-face-muted text-danger';
        error.textContent = message;
        list.append(error);
    };

    const loadPeople = async (term = '') => {
        const requestId = ++listRequestId;
        if (listController) listController.abort();
        listController = new AbortController();
        list.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(`${apiUrl}?q=${encodeURIComponent(term.trim())}`, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
                signal: listController.signal,
            });
            const result = await response.json();
            if (!response.ok || result.ok !== true) {
                throw new Error(result.message || t('Não foi possível carregar os usuários.'));
            }
            if (requestId !== listRequestId) return;
            people = Array.isArray(result.pessoas) ? result.pessoas : [];
            renderPeople();
        } catch (error) {
            if (error.name === 'AbortError' || requestId !== listRequestId) return;
            showListError(error.message || t('Falha ao carregar os usuários.'));
        } finally {
            if (requestId === listRequestId) list.removeAttribute('aria-busy');
        }
    };

    const startCamera = async () => {
        if (!selected || saving) return;
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            setFeedback(t('A câmera requer HTTPS ou localhost e suporte do navegador.'), 'error');
            return;
        }

        const targetId = selected.id;
        const requestId = ++cameraRequestId;
        startButton.disabled = true;
        setFeedback(t('Solicitando acesso à câmera…'));
        try {
            const newStream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: {
                    facingMode: 'user',
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                },
            });
            if (requestId !== cameraRequestId || selected?.id !== targetId) {
                newStream.getTracks().forEach(track => track.stop());
                return;
            }

            stream = newStream;
            video.srcObject = stream;
            video.hidden = false;
            placeholder.hidden = true;
            await video.play();
            if (requestId !== cameraRequestId || selected?.id !== targetId) return;
            captureButton.disabled = false;
            startButton.hidden = true;
            stopButton.hidden = false;
            setFeedback(t('Câmera ativa. Posicione a pessoa e capture a foto.'));
        } catch (error) {
            if (requestId !== cameraRequestId) return;
            releaseCamera();
            startButton.hidden = false;
            startButton.disabled = !selected;
            const messages = {
                NotAllowedError: t('Acesso à câmera negado. Permita o uso da câmera nas configurações do navegador.'),
                PermissionDeniedError: t('Acesso à câmera negado. Permita o uso da câmera nas configurações do navegador.'),
                NotFoundError: t('Nenhuma câmera foi encontrada neste dispositivo.'),
                DevicesNotFoundError: t('Nenhuma câmera foi encontrada neste dispositivo.'),
                NotReadableError: t('A câmera está em uso por outro aplicativo ou não pode ser acessada.'),
                OverconstrainedError: t('A câmera não oferece as configurações necessárias.'),
            };
            setFeedback(messages[error.name] || t('Não foi possível ativar a câmera. Verifique o dispositivo e tente novamente.'), 'error');
        }
    };

    const capturePhoto = () => {
        if (!selected || !stream || !video.videoWidth || !video.videoHeight) {
            setFeedback(t('A câmera ainda não está pronta para capturar.'), 'error');
            return;
        }
        const targetId = selected.id;
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const context = canvas.getContext('2d');
        if (!context) {
            setFeedback(t('Não foi possível preparar a captura da imagem.'), 'error');
            return;
        }
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(blob => {
            if (selected?.id !== targetId) return;
            if (!blob || blob.type !== 'image/jpeg' || blob.size < 1 || blob.size > 5 * 1024 * 1024) {
                setFeedback(t('A imagem não foi capturada corretamente ou excede 5 MB. Tente novamente.'), 'error');
                return;
            }
            capturedImage = blob;
            previewUrl = URL.createObjectURL(blob);
            preview.src = previewUrl;
            preview.hidden = false;
            releaseCamera();
            startButton.hidden = true;
            captureButton.hidden = true;
            retakeButton.hidden = false;
            cancelButton.hidden = false;
            saveButton.hidden = false;
            saveButton.disabled = false;
            confirmation.textContent = `${t('Você está cadastrando a face de')}: ${selected.nome} — ID ${selected.id}.`;
            confirmation.hidden = false;
            setFeedback(t('Foto capturada. Confira o nome e a pré-visualização antes de salvar.'));
        }, 'image/jpeg', 0.85);
    };

    const savePhoto = async () => {
        if (!selected || !capturedImage || saving) return;
        saving = true;
        const targetId = selected.id;
        saveButton.disabled = true;
        startButton.disabled = true;
        if (search) search.disabled = true;
        retakeButton.disabled = true;
        cancelButton.disabled = true;
        renderPeople();
        setFeedback(t('Enviando imagem…'));

        const data = new FormData();
        data.append('csrf_token', csrfToken);
        data.append('id_usuario', String(targetId));
        data.append('imagem', capturedImage, 'captura-facial.jpg');

        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                body: data,
            });
            const result = await response.json();
            if (!response.ok || result.ok !== true) {
                throw new Error(result.message || t('Não foi possível salvar o cadastro facial.'));
            }
            if (selected?.id !== targetId) return;
            selected.foto_cadastrada = true;
            setFacialStatus(true);
            renderPeople();
            resetCaptureStep(result.message || t('Cadastro facial salvo com sucesso.'));
            setFeedback(result.message || t('Cadastro facial salvo com sucesso.'), 'success');
        } catch (error) {
            setFeedback(error.message || t('Falha ao salvar o cadastro facial. Tente novamente.'), 'error');
        } finally {
            saving = false;
            saveButton.disabled = !capturedImage;
            startButton.disabled = !selected;
            if (search) search.disabled = false;
            retakeButton.disabled = false;
            cancelButton.disabled = false;
            renderPeople();
        }
    };

    if (search) {
        search.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadPeople(search.value), 250);
        });
    }
    startButton.addEventListener('click', startCamera);
    captureButton.addEventListener('click', capturePhoto);
    stopButton.addEventListener('click', () => resetCaptureStep(t('Câmera desligada.')));
    retakeButton.addEventListener('click', () => resetCaptureStep(t('Captura descartada. Ative a câmera para tentar novamente.')));
    cancelButton.addEventListener('click', () => {
        selected = null;
        resetCaptureStep(t('Seleção cancelada. Escolha outra pessoa para continuar.'));
        updateSelectedPerson();
        renderPeople();
    });
    saveButton.addEventListener('click', savePhoto);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && stream) resetCaptureStep(t('Câmera desligada ao sair da tela.'));
    });
    window.addEventListener('pagehide', releaseCamera);
    window.addEventListener('beforeunload', releaseCamera);

    loadPeople();
});
