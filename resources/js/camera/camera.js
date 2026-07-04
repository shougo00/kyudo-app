let video, canvas, ctx, pose;
let latestLandmarks = null;
let prevLandmarks = null;
let smoothLandmarksData = null;

let isProcessing = false;
let lastPoseTime = 0;

let currentStream = null;
let currentFacingMode = "environment";

const CHECKPOINTS = ["胴作り", "打起こし", "第三", "会", "離れ"];
let currentStep = 0;
let currentPhase = CHECKPOINTS[0];

let phaseHitCount = 0;
let lastPhaseChangeTime = 0;
let stillStart = null;
let kaiStartTime = null;   // 会に入った時間
let measuredData = null;   // 1秒後の角度データ
let measuredDone = false;  // 1秒経過したか
let resultHandled = false;
let leftTargetDirection = true;
let measureStartTime = null;
let measureBuffer = [];
let kaiEnterTime = null;

document.addEventListener('DOMContentLoaded', () => {
    video = document.getElementById('camera');
    canvas = document.getElementById('canvas');
    ctx = canvas.getContext('2d');

    pose = new Pose({
        locateFile: f => `https://cdn.jsdelivr.net/npm/@mediapipe/pose/${f}`
    });

    pose.setOptions({
        modelComplexity: 1,
        smoothLandmarks: true,
        enableSegmentation: false,
        smoothSegmentation: false,
        minDetectionConfidence: 0.7,
        minTrackingConfidence: 0.7
    });

    pose.onResults(res => {
        latestLandmarks = res.poseLandmarks;
    });

    renderCheckpoints();
});

// ===== カメラ起動 PC・スマホ両対応 =====
async function startCamera() {
    try {
        stopCamera();

        let stream = null;

        try {
            // スマホ優先：背面カメラ
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: currentFacingMode },
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            });
        } catch (e) {
            // PC用：通常カメラ
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            });
        }

        currentStream = stream;
        video.srcObject = stream;

        await video.play();

        document.getElementById('cameraInfo').innerText =
            currentFacingMode === "environment"
                ? "カメラ起動中：背面カメラ優先 / PCでは通常カメラ"
                : "カメラ起動中：前面カメラ優先 / PCでは通常カメラ";

        requestAnimationFrame(loop);

    } catch (error) {
        console.error(error);
        alert(
            'カメラを起動できません。\n\n' +
            '確認してください：\n' +
            '・HTTPSで開いているか\n' +
            '・ブラウザでカメラ許可しているか\n' +
            '・他アプリがカメラを使っていないか\n' +
            '・PCにカメラが接続されているか'
        );
    }
}

// ===== カメラ停止 =====
function stopCamera() {
    if (currentStream) {
        currentStream.getTracks().forEach(track => track.stop());
        currentStream = null;
    }
}

// ===== カメラ切替 =====
async function switchCamera() {
    currentFacingMode = currentFacingMode === "environment" ? "user" : "environment";
    await startCamera();
}

// ===== メインループ =====
function loop(time) {
    if (!video || !video.videoWidth || !video.videoHeight) {
        requestAnimationFrame(loop);
        return;
    }

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;

    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    if (time - lastPoseTime > 100 && !isProcessing) {
        isProcessing = true;
        lastPoseTime = time;

        pose.send({ image: video }).finally(() => {
            isProcessing = false;
        });
    }

    if (latestLandmarks) {
        const lm = smoothLandmarks(latestLandmarks);

        if (!hasRequiredPoints(lm)) {
            requestAnimationFrame(loop);
            return;
        }

drawSkeleton(lm);

        const m = calcMetrics(lm);
        // ===== 会の平均用データ収集 =====
            // 会に入って0.5秒後から記録する
            if (currentPhase === "会" && kaiEnterTime) {

                const now = Date.now();
                const elapsed = now - kaiEnterTime;

                if (elapsed >= 500) {
                    measureBuffer.push({
                        time: now,
                        rightElbow: m.rightElbowAngle,
                        rightArmpit: m.rightArmpitAngle,
                        leftArmpit: m.leftArmpitAngle
                    });
                }
            }
        drawAngles(lm, m);
        updatePhase(m);

        document.getElementById('phase').innerText = currentPhase;

        document.getElementById('metrics').innerHTML = `
            左肘:${m.leftElbowAngle.toFixed(1)}°<br>
            右肘:${m.rightElbowAngle.toFixed(1)}°<br>
            左脇:${m.leftArmpitAngle.toFixed(1)}°<br>
            右脇:${m.rightArmpitAngle.toFixed(1)}°<br>
            手幅:${m.handWidth.toFixed(3)}<br>
            足幅:${m.footWidth.toFixed(3)}<br>
            静止:${m.stillTime}ms<br>
            
            
        `;
    }

    requestAnimationFrame(loop);
}

// ===== 角度表示 =====
function drawAngles(lm, m) {
    ctx.font = "20px Arial";
    ctx.lineWidth = 4;

    drawTextAtPoint(lm[13], `左肘 ${m.leftElbowAngle.toFixed(0)}°`, "yellow");
    drawTextAtPoint(lm[14], `右肘 ${m.rightElbowAngle.toFixed(0)}°`, "yellow");
    drawTextAtPoint(lm[11], `左脇 ${m.leftArmpitAngle.toFixed(0)}°`, "orange");
    drawTextAtPoint(lm[12], `右脇 ${m.rightArmpitAngle.toFixed(0)}°`, "orange");

    const leftArmAngle = lineAngle(lm[11], lm[15]);
    const rightArmAngle = lineAngle(lm[12], lm[16]);

    drawTextAtPoint(lm[15], `左腕 ${leftArmAngle.toFixed(0)}°`, "cyan");
    drawTextAtPoint(lm[16], `右腕 ${rightArmAngle.toFixed(0)}°`, "cyan");
}

function drawTextAtPoint(p, text, color) {
    if (!p) return;

    const x = p.x * canvas.width;
    const y = p.y * canvas.height;

    ctx.strokeStyle = "black";
    ctx.fillStyle = color;

    ctx.strokeText(text, x + 8, y - 8);
    ctx.fillText(text, x + 8, y - 8);
}

function lineAngle(a, b) {
    if (!a || !b) return 0;

    const dx = (b.x * canvas.width) - (a.x * canvas.width);
    const dy = (b.y * canvas.height) - (a.y * canvas.height);

    let deg = Math.atan2(dy, dx) * 180 / Math.PI;

    if (deg < 0) deg += 360;

    return deg;
}

// ===== 必要点チェック =====
function hasRequiredPoints(lm) {
    const required = [0,11,12,13,14,15,16,23,24];
    return required.every(i => lm[i]);
}

// ===== なめらか化 =====
function smoothLandmarks(lm) {
    if (!smoothLandmarksData) {
        smoothLandmarksData = JSON.parse(JSON.stringify(lm));
        return smoothLandmarksData;
    }

    for (let i = 0; i < lm.length; i++) {
        if (!lm[i] || !smoothLandmarksData[i]) continue;

        smoothLandmarksData[i].x = smoothLandmarksData[i].x * 0.7 + lm[i].x * 0.3;
        smoothLandmarksData[i].y = smoothLandmarksData[i].y * 0.7 + lm[i].y * 0.3;
        smoothLandmarksData[i].z = smoothLandmarksData[i].z * 0.7 + lm[i].z * 0.3;
        smoothLandmarksData[i].visibility = lm[i].visibility;
    }

    return smoothLandmarksData;
}

// ===== 数値計算 =====
function calcMetrics(lm) {
    const noseY = lm[0].y;

    const leftShoulderX = lm[11].x;
    const leftShoulderY = lm[11].y;
    const rightShoulderX = lm[12].x;
    const rightShoulderY = lm[12].y;
    const shoulderY = (leftShoulderY + rightShoulderY) / 2;

    const leftHandX = lm[15].x;
    const leftHandY = lm[15].y;
    const rightHandX = lm[16].x;
    const rightHandY = lm[16].y;

    const rightElbowX = lm[14].x;
    const rightElbowY = lm[14].y;

    const leftElbowAngle = safeAngle(lm[11], lm[13], lm[15]);
    const rightElbowAngle = safeAngle(lm[12], lm[14], lm[16]);

    const handWidth = Math.abs(leftHandX - rightHandX);
    const leftArmpitAngle  = safeAngle(lm[13], lm[11], lm[23]); // 左脇
    const rightArmpitAngle = safeAngle(lm[14], lm[12], lm[24]); // 右脇

    let footWidth = 0;
    if (lm[31] && lm[32]) {
        footWidth = Math.abs(lm[31].x - lm[32].x);
    }

    let move = 0;
    let rightHandDownMove = 0;
    let rightElbowSideMove = 0;

    if (prevLandmarks) {
        move =
            Math.abs(lm[15].x - prevLandmarks[15].x) +
            Math.abs(lm[15].y - prevLandmarks[15].y) +
            Math.abs(lm[16].x - prevLandmarks[16].x) +
            Math.abs(lm[16].y - prevLandmarks[16].y);

        rightHandDownMove = rightHandY - prevLandmarks[16].y;
        rightElbowSideMove = Math.abs(rightElbowX - prevLandmarks[14].x);
    }

    prevLandmarks = JSON.parse(JSON.stringify(lm));

    let stillTime = 0;
    if (move < 0.004) {
        if (!stillStart) stillStart = Date.now();
        stillTime = Date.now() - stillStart;
    } else {
        stillStart = null;
    }

    return {
        noseY,

        leftShoulderX,
        leftShoulderY,
        rightShoulderX,
        rightShoulderY,
        shoulderY,

        leftArmpitAngle,
        rightArmpitAngle,

        leftHandX,
        leftHandY,
        rightHandX,
        rightHandY,

        rightElbowX,
        rightElbowY,

        leftElbowAngle,
        rightElbowAngle,

        handWidth,
        footWidth,
        move,
        stillTime,

        rightHandDownMove,
        rightElbowSideMove
    };
}

// ===== 判定ロジック =====
function updatePhase(m) {
    if (currentPhase === "胴作り") {
        judge(
            m.leftHandY < m.noseY - 0.02 &&
            m.rightHandY < m.noseY - 0.02 &&
            m.handWidth > 0.12
        );
    }

    else if (currentPhase === "打起こし") {

        let diff = m.leftHandX - m.leftShoulderX;

        let leftHandOutside = diff > 0.1;//ここ変えれば右手の位置調整
       

        judge(
            m.leftElbowAngle >= 160 &&
            m.leftElbowAngle <= 180 &&
            leftHandOutside
        );
    }

    else if (currentPhase === "第三") {
        judge(
            m.move < 0.004 &&
            m.stillTime > 700 &&
            m.handWidth > 0.25 &&
            m.rightArmpitAngle <= 115 &&
            m.leftArmpitAngle <= 115
        );
    }
    else if (currentPhase === "会") {

        // 離れ判定：右肘90度以上
        const releaseDetected = m.rightElbowAngle >= 90;

        if (releaseDetected && !resultHandled) {

            resultHandled = true;

            const releaseTime = Date.now();
            const kaiTime = kaiStartTime ? releaseTime - kaiStartTime : 0;

            // 離れ直前0.5秒を除外
            const validBuffer = measureBuffer.filter(d => {
                return d.time <= releaseTime - 500;
            });

            if (validBuffer.length === 0) {
                alert("平均データが不足しています。もう少し長く会を保ってください。");
                resetPhase();
                return;
            }

            let sumElbow = 0;
            let sumRight = 0;
            let sumLeft = 0;

            validBuffer.forEach(d => {
                sumElbow += d.rightElbow;
                sumRight += d.rightArmpit;
                sumLeft += d.leftArmpit;
            });

            const count = validBuffer.length;

            measuredData = {
                rightElbow: sumElbow / count,
                rightArmpit: sumRight / count,
                leftArmpit: sumLeft / count
            };

            currentPhase = "離れ";
            currentStep = CHECKPOINTS.indexOf("離れ");
            renderCheckpoints();

            setTimeout(async () => {
                const ok = confirm(
                    "保存しますか？\n\n" +
                    "右肘: " + measuredData.rightElbow.toFixed(1) + "°\n" +
                    "右脇: " + measuredData.rightArmpit.toFixed(1) + "°\n" +
                    "左脇: " + measuredData.leftArmpit.toFixed(1) + "°\n" +
                    "会時間: " + (kaiTime / 1000).toFixed(2) + "秒"
                );

                if (ok) {
                    await saveResult(kaiTime);
                }

                resetPhase();

                latestLandmarks = null;
                prevLandmarks = null;
                smoothLandmarksData = null;
                measureBuffer = [];
                measuredData = null;
                kaiStartTime = null;
                kaiEnterTime = null;

                // スマホ対策：止まった処理を強制解除
                isProcessing = false;
                lastPoseTime = 0;

                if (video && video.srcObject) {
                    await video.play().catch(() => {});
                    requestAnimationFrame(loop);
                }

            }, 2000);
        }
    }

}

// ===== 連続判定 =====
function judge(condition) {
    if (condition) {
        phaseHitCount++;
    } else {
        phaseHitCount = 0;
    }

    if (phaseHitCount >= 5) {
        phaseHitCount = 0;

        if (currentPhase === "第三") {
            save("第三", lastMetricsSafe());

            kaiStartTime = Date.now();
            kaiEnterTime = Date.now();

            measureBuffer = [];
            measuredData = null;
            resultHandled = false;
        }
        next();
    }
}

function lastMetricsSafe() {
    if (!latestLandmarks) return {};
    return calcMetrics(smoothLandmarks(latestLandmarks));
}

// ===== 次へ =====
function next() {
    const now = Date.now();

    if (now - lastPhaseChangeTime < 800) return;

    if (currentStep < CHECKPOINTS.length - 1) {
        currentStep++;
        currentPhase = CHECKPOINTS[currentStep];
        lastPhaseChangeTime = now;
        stillStart = null;
        renderCheckpoints();
    }
}

// ===== リセット =====
function resetPhase() {
    currentStep = 0;
    currentPhase = CHECKPOINTS[0];

    phaseHitCount = 0;
    lastPhaseChangeTime = 0;
    stillStart = null;
    prevLandmarks = null;
    smoothLandmarksData = null;

    renderCheckpoints();
    document.getElementById('phase').innerText = currentPhase;
}

// ===== 向き切替 =====
function toggleDirection() {
    leftTargetDirection = !leftTargetDirection;

    alert(
        leftTargetDirection
            ? '向き：左手が画面左へ伸びる設定'
            : '向き：左手が画面右へ伸びる設定'
    );
}

// ===== チェックポイント表示 =====
function renderCheckpoints() {
    const html = CHECKPOINTS.map((p, i) => {
        if (i < currentStep) return `${p} ✓`;
        if (i === currentStep) return `<b style="color:yellow;">${p}</b>`;
        return p;
    }).join(' → ');

    // 下の表示（そのまま）
    const bottom = document.getElementById('checkpoints');
    if (bottom) bottom.innerHTML = html;

    // 上のカメラ表示
    const overlay = document.getElementById('overlayCheckpoints');
    if (overlay) overlay.innerHTML = html;
}

// ===== 保存 =====
function save(phase, m) {
    if (!m || !m.leftElbowAngle) return;

    fetch('/kyudo-pose-records', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            phase: phase,

            left_elbow_angle: m.leftElbowAngle,
            right_elbow_angle: m.rightElbowAngle,

            hand_width: m.handWidth,
            foot_width: m.footWidth,

            move: m.move,
            still_time: m.stillTime,

            left_hand_x: m.leftHandX,
            left_hand_y: m.leftHandY,
            right_hand_x: m.rightHandX,
            right_hand_y: m.rightHandY
        })
    }).catch(() => {});
}

// ===== 骨格描画 =====
function drawSkeleton(lm) {
    const lines = [
        [11,13], [13,15],
        [12,14], [14,16],
        [11,12],
        [11,23], [12,24], [23,24],
        [23,25], [25,27], [27,31],
        [24,26], [26,28], [28,32]
    ];

    ctx.strokeStyle = "lime";
    ctx.lineWidth = 2;

    lines.forEach(([a, b]) => {
        const p1 = lm[a];
        const p2 = lm[b];
        if (!p1 || !p2) return;

        ctx.beginPath();
        ctx.moveTo(p1.x * canvas.width, p1.y * canvas.height);
        ctx.lineTo(p2.x * canvas.width, p2.y * canvas.height);
        ctx.stroke();
    });

    ctx.fillStyle = "red";
    [0,11,12,13,14,15,16,23,24,25,26,27,28,31,32].forEach(i => {
        if (!lm[i]) return;

        ctx.beginPath();
        ctx.arc(lm[i].x * canvas.width, lm[i].y * canvas.height, 4, 0, Math.PI * 2);
        ctx.fill();
    });
}

// ===== 角度 =====
function safeAngle(a, b, c) {
    if (!a || !b || !c) return 0;

    const ax = a.x * canvas.width;
    const ay = a.y * canvas.height;
    const bx = b.x * canvas.width;
    const by = b.y * canvas.height;
    const cx = c.x * canvas.width;
    const cy = c.y * canvas.height;

    const ab = { x: ax - bx, y: ay - by };
    const cb = { x: cx - bx, y: cy - by };

    const dot = ab.x * cb.x + ab.y * cb.y;

    const mag =
        Math.sqrt(ab.x ** 2 + ab.y ** 2) *
        Math.sqrt(cb.x ** 2 + cb.y ** 2);

    if (!mag) return 0;

    let cos = dot / mag;
    cos = Math.max(-1, Math.min(1, cos));

    return Math.acos(cos) * 180 / Math.PI;
}
function saveResult(kaiTime) {

    if (!measuredData) {
        alert("データ未取得");
        return Promise.resolve();
    }

    return fetch('/kyudo-results', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            kai_time: kaiTime,
            right_elbow_angle: measuredData.rightElbow,
            right_armpit_angle: measuredData.rightArmpit,
            left_armpit_angle: measuredData.leftArmpit
        })
    })
    .then(() => {
        console.log("保存成功");
    })
    .catch(() => {
        console.error("保存失敗");
    });
}
window.startCamera = startCamera;
window.resetPhase = resetPhase;
window.switchCamera = switchCamera;
window.toggleDirection = toggleDirection;