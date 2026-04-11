let audio = null;
let unlocked = false;
let intervalId = null;

export function getAudio() {
  if (!audio) {
    audio = new Audio("/sounds/notification.wav");
    audio.preload = "auto";
  }
  return audio;
}

export async function unlockAudio() {
  if (unlocked) return true;

  try {
    const a = getAudio();
    a.volume = 0;
    await a.play(); // must be user gesture
    a.pause();
    a.currentTime = 0;
    a.volume = 1;
    unlocked = true;
    return true;
  } catch {
    return false;
  }
}

export function startRingtone() {
  if (!unlocked || intervalId) return;

  intervalId = window.setInterval(() => {
    const a = getAudio();
    a.currentTime = 0;
    a.play().catch(() => {});
  }, 3000);
}

export function stopRingtone() {
  if (intervalId) {
    clearInterval(intervalId);
    intervalId = null;
  }
}
