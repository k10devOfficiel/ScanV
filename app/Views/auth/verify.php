<div class="auth-wrapper">
    <div class="auth-card-box">
        <!-- Top Branding Logo -->
        <div style="display:flex;flex-direction:column;align-items:center;gap:8px;margin-bottom:24px">
            <svg width="48" height="48" viewBox="0 0 21 21" aria-hidden="true" shape-rendering="crispEdges" fill="var(--color-primary, #4338CA)">
                <path fill-rule="evenodd" d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zM15 1v5h5V1zM16 2h3v3h-3zM0 14h7v7H0zM1 15v5h5v-5zM2 16h3v3H2z"></path>
                <path d="M8 9h1v3H8zM12 9h1v3h-1zM9 12h1v1H9zM11 12h1v1h-1zM10 13h1v1h-1zM15 15h1v1h-1zM17 17h1v1h-1zM19 15h1v1h-1zM9 16h1v1H9zM12 18h2v1h-2z"></path>
            </svg>
            <span style="font:700 26px Poppins;color:var(--color-primary, #4338CA)">ScanV</span>
        </div>

        <!-- Form Card Container (.c) -->
        <div class="c" style="display:flex;flex-direction:column;gap:18px">
            <div>
                <h1 style="font-size:24px;margin-bottom:6px">Vérification du code</h1>
                <p style="color:var(--color-text-muted, #475569);font-size:14px;line-height:1.5">
                    Un code de sécurité à 6 chiffres a été envoyé à <strong style="color:var(--color-text, #0F172A)"><?= e($maskedEmail ?? '') ?></strong>.
                    Il expire dans 10 minutes.
                </p>
            </div>

            <?php if (!empty($flashSuccess)): ?>
                <div class="alert alert-success" role="alert" aria-live="polite">
                    <?= e($flashSuccess) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($flashError)): ?>
                <div class="alert alert-danger" role="alert" aria-live="polite">
                    <?= e($flashError) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert" aria-live="polite">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <!-- OTP Submission Form -->
            <form id="otp-form" method="POST" action="<?= e(url('/forgot/verify')) ?>" style="display:flex;flex-direction:column;gap:20px" novalidate>
                <?= csrf_field() ?>

                <div style="display:flex;flex-direction:column;gap:8px;align-items:center">
                    <label for="otp-input-0" style="font-size:14px;font-weight:600;color:var(--color-text, #0F172A);align-self:flex-start">
                        Code à 6 chiffres
                    </label>

                    <div class="otp-inputs-row" style="display:flex;gap:8px;justify-content:center;width:100%;max-width:320px">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                            <input
                                type="text"
                                class="otp-digit"
                                id="otp-input-<?= $i ?>"
                                name="otp_digits[<?= $i ?>]"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="1"
                                autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>"
                                data-index="<?= $i ?>"
                                <?= $i === 0 ? 'autofocus' : '' ?>
                                style="width:44px;height:52px;font-size:22px;font-weight:700;text-align:center;border:1.5px solid var(--color-border-input, #CBD5E1);border-radius:10px;background:#fff;color:var(--color-text, #0F172A);transition:border-color 0.15s,box-shadow 0.15s"
                                required
                            >
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="otp" id="otp-hidden-field" value="">
                </div>

                <button type="submit" id="btn-submit-otp" class="b p b-lg b-full">Valider le code</button>
            </form>

            <!-- Resend Section & Countdown -->
            <div style="display:flex;flex-direction:column;align-items:center;gap:10px;padding-top:8px;border-top:1px solid var(--color-border, #E2E8F0)">
                <div id="resend-countdown-box" style="font-size:13px;color:var(--color-text-muted, #475569);text-align:center">
                    <?php $sec = (int) ($remainingSeconds ?? 0); ?>
                    <?php if ($sec > 0): ?>
                        <span id="countdown-wrapper">Renvoyer le code dans <strong id="countdown-timer"><?= $sec ?></strong> s</span>
                    <?php else: ?>
                        <span id="countdown-wrapper">Tu n'as pas reçu le code ?</span>
                    <?php endif; ?>
                </div>

                <form id="resend-form" method="POST" action="<?= e(url('/forgot/resend')) ?>">
                    <?= csrf_field() ?>
                    <button
                        type="submit"
                        id="resend-btn"
                        class="b b-sm"
                        <?= $sec > 0 ? 'disabled style="opacity:0.5;cursor:not-allowed"' : '' ?>
                    >
                        Renvoyer un nouveau code
                    </button>
                </form>

                <a href="<?= e(url('/forgot')) ?>" style="font-size:13px;color:var(--color-primary, #4338CA);font-weight:600;margin-top:4px">
                    Changer d'e-mail
                </a>
            </div>

            <a href="<?= e(url('/login')) ?>" style="text-align:center;font-size:13px;color:var(--color-text-muted, #64748B);margin-top:4px">
                ← Annuler et retourner à la connexion
            </a>
        </div>
    </div>
</div>

<script>
(function() {
    var digits = Array.from(document.querySelectorAll('.otp-digit'));
    var hiddenOtp = document.getElementById('otp-hidden-field');
    var form = document.getElementById('otp-form');

    function syncHiddenOtp() {
        var val = digits.map(function(d) { return d.value.trim(); }).join('');
        if (hiddenOtp) hiddenOtp.value = val;
        return val;
    }

    digits.forEach(function(input, idx) {
        input.addEventListener('focus', function() {
            this.select();
        });

        input.addEventListener('input', function(e) {
            var val = this.value.replace(/[^0-9]/g, '');
            this.value = val ? val.slice(-1) : '';

            var fullVal = syncHiddenOtp();

            if (this.value && idx < digits.length - 1) {
                digits[idx + 1].focus();
                digits[idx + 1].select();
            }

            if (fullVal.length === 6) {
                form.submit();
            }
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !this.value && idx > 0) {
                digits[idx - 1].focus();
                digits[idx - 1].select();
            }
        });

        input.addEventListener('paste', function(e) {
            e.preventDefault();
            var pasted = (e.clipboardData || window.clipboardData).getData('text');
            if (!pasted) return;
            var numbers = pasted.replace(/[^0-9]/g, '').slice(0, 6);
            if (numbers.length > 0) {
                for (var i = 0; i < digits.length; i++) {
                    digits[i].value = numbers[i] || '';
                }
                var val = syncHiddenOtp();
                var focusIdx = Math.min(numbers.length, digits.length - 1);
                digits[focusIdx].focus();
                if (val.length === 6) {
                    form.submit();
                }
            }
        });
    });

    // Countdown timer handling
    var timerEl = document.getElementById('countdown-timer');
    var resendBtn = document.getElementById('resend-btn');
    var countdownWrapper = document.getElementById('countdown-wrapper');

    if (timerEl && resendBtn) {
        var secondsLeft = parseInt(timerEl.textContent, 10) || 0;
        if (secondsLeft > 0) {
            var interval = setInterval(function() {
                secondsLeft--;
                if (secondsLeft <= 0) {
                    clearInterval(interval);
                    if (countdownWrapper) {
                        countdownWrapper.textContent = "Tu n'as pas reçu le code ?";
                    }
                    resendBtn.removeAttribute('disabled');
                    resendBtn.style.opacity = '1';
                    resendBtn.style.cursor = 'pointer';
                } else {
                    timerEl.textContent = secondsLeft;
                }
            }, 1000);
        }
    }
})();
</script>
