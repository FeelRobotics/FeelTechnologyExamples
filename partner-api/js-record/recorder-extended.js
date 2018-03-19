/* global feelrecord, $, MediaRecorder, $feel, window, navigator, document, Blob */


window.runRecorder = function runRecorder(subsToken, appsToken, videoId, feelAppUserId) {
  // Settings used to obtain access to camera/microphone via getUserMedia
  const constraints = {
    audio: true,
    video: {
      width: {
        min: 640,
        ideal: 640,
        max: 640,
      },
      height: {
        min: 480,
        ideal: 480,
        max: 480,
      },
    },
  };
  const videoElement = document.querySelector('video');
  const downloadLink = document.querySelector('a#downloadLink');

  // Date/time when recording has started
  let startRecordingTime = 0;
  // Video position in seconds when recording has been paused
  let pauseTime = 0;
  // Interval to update video recording time in UI
  let interval = null;
  // MediaRecorder HTML5 object
  let mediaRecorder = null;

  // Current mode.
  // There are 4 modes:
  // idleMode - initial mode, no recording or video playing performed
  // recordingMode - recording video and subtitles
  // recordingPauseMode - recording is paused
  // playMode - video and subtitles are being played
  let mode = null;

  /**
   * Function called in case of MediaRecorder error
   * @param {string} error error message
   */
  function errorCallback(error) {
    console.log('navigator.getUserMedia error: ', error);
  }

  /**
   * Start updating recording time in UI
   * @param {int} startRecTime - initial time in seconds
   */
  function startTimer(startRecTime) {
    clearInterval(interval);
    interval = setInterval(() => {
      const time = Math.floor(((new Date()).getTime() / 1000) - startRecTime);
      const secs = (time % 60 < 10 ? '0' : '') + (time % 60);
      const mins = Math.floor(time / 60);
      $('.counter').text(`${mins}:${secs}`);
    }, 200);
  }

  /**
   * Stop updating recording time in UI
   */
  function stopTimer() {
    clearInterval(interval);
  }

  /**
   * Function to be called when stop recording button is pressed
   */
  function stopButtonPressed() {
    $('#finish').hide();
    $('#pause').hide();
    $('#start').hide();
    feelrecord.stop();
    feelrecord.save(subsToken);
    stopTimer();
    mediaRecorder.stop();
    // TODO use jquery
    videoElement.controls = true;
    mode = playMode;
    playMode.init();
  }

  // Chunks which keep video file data
  let chunks = [];

  /**
   * Start video recording, used as callback for getUserMedia
   * @param {MediaStream} stream - Object containing media stream
   */
  function startRecording(stream) {
    if (typeof MediaRecorder.isTypeSupported === 'function') {
      let options = null;
      if (MediaRecorder.isTypeSupported('video/webm;codecs=vp9')) {
        options = { mimeType: 'video/webm;codecs=vp9' };
      } else if (MediaRecorder.isTypeSupported('video/webm;codecs=h264')) {
        options = { mimeType: 'video/webm;codecs=h264' };
      } else if (MediaRecorder.isTypeSupported('video/webm;codecs=vp8')) {
        options = { mimeType: 'video/webm;codecs=vp8' };
      }
      mediaRecorder = new MediaRecorder(stream, options);
    } else {
      mediaRecorder = new MediaRecorder(stream);
    }

    // 10 is media chunk duration in milliseconds
    mediaRecorder.start(10);

    const url = window.URL || window.webkitURL;
    videoElement.src = url ? url.createObjectURL(stream) : stream;
    videoElement.play();

    mediaRecorder.ondataavailable = function ondataavailable(e) {
      chunks.push(e.data);
    };

    mediaRecorder.onerror = function onerror(e) {
      console.error('Error: ', e);
    };

    mediaRecorder.onstart = function onstart() {
      // Actual video recording starts here
      const timeSeconds = 0;
      feelrecord.record(timeSeconds);
      startRecordingTime = (new Date()).getTime() / 1000;
      startTimer(startRecordingTime);
    };

    mediaRecorder.onstop = function onstop() {
      const blob = new Blob(chunks, { type: 'video/webm' });
      chunks = [];

      const videoURL = window.URL.createObjectURL(blob);

      downloadLink.href = videoURL;
      videoElement.src = videoURL;
      downloadLink.innerHTML = 'Download video file';

      const rand = Math.floor((Math.random() * 10000000));
      const name = `video_${rand}.webm`;

      downloadLink.setAttribute('download', name);
      downloadLink.setAttribute('name', name);
    };
  }

  // video and recording is stopped
  const idleMode = {
    onRecordButton() {
      $('#finish').show();
      $('#pause').show();
      $('#start').hide();
      navigator.getUserMedia(constraints, startRecording, errorCallback);
      mode = recordingMode;
    },
  };

  // video is being recorded
  const recordingMode = {
    onPauseButton() {
      $('#stop').hide();
      $('#pause').hide();
      $('#start').show();
      feelrecord.stop();
      clearInterval(interval);
      mediaRecorder.pause();
      pauseTime = ((new Date()).getTime() / 1000) - startRecordingTime;
      mode = recordingPauseMode;
    },
    onStopButton() {
      stopButtonPressed();
    },
  };

  // video recording is paused
  const recordingPauseMode = {
    onRecordButton() {
      $('#finish').show();
      $('#pause').show();
      $('#start').hide();
      feelrecord.record(pauseTime); // Overwrite everything after pauseTime
      startRecordingTime = ((new Date()).getTime() / 1000) - pauseTime;
      startTimer(startRecordingTime);
      mediaRecorder.resume();
      mode = recordingMode;
    },
    onStopButton() {
      stopButtonPressed();
    },
  };

  // video is being played
  const playMode = {
    init() {
      $feel.init(subsToken, appsToken, feelAppUserId);
      $feel.subs.load(videoId, 0, feelAppUserId)
        .then(() => {
          console.log('Subtitles loaded');
        }).catch((error) => {
          console.log('Error loading subtitles: ', error);
        });

      $feel.subs.events.on('subtitle', (percent) => {
        $('#device-value').width(`${percent}%`);
      });

      // Handle play/pause events from the video player
      $('video').on('play', function onVideoPlay() {
        const currentTimeInSeconds = this.currentTime;
        $feel.subs.play(currentTimeInSeconds);
      }).on('timeupdate', function onVideoTimeUpdate() {
        const currentTimeInSeconds = this.currentTime;
        $feel.subs.timeupdate(currentTimeInSeconds);
      }).on('pause', () => {
        $feel.subs.stop();
      });
    },
  };

  // Start in the idle mode initially
  mode = idleMode;

  // Initialize recording library
  feelrecord.init(appsToken);

  // Initilize UI
  $('#finish').hide();
  $('#pause').hide();
  videoElement.controls = false;

  // Setup UI buttons handlers
  $('#start').click(() => {
    mode.onRecordButton();
  });

  $('#pause').click(() => {
    mode.onPauseButton();
  });

  $('#finish').click(() => {
    mode.onStopButton();
  });

  // Update UI on every signal coming from Bluetooth devices
  feelrecord.onData((percent) => {
    console.log('Incoming value', percent);
    $('#device-value').width(`${percent}%`);
  });
};
