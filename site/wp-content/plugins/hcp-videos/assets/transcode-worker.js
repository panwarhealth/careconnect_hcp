/**
 * Converts a chosen video into a web-ready MP4: H.264 video no larger than
 * 1080p on its short side, AAC audio, and the index at the front so playback
 * starts before the download finishes. Files that already meet that are
 * copied without re-encoding (seconds, not minutes).
 *
 * Runs as a module worker so it keeps full speed while the tab is in the
 * background. Message in: { file, libraryUrl }. Messages out:
 * { type: 'progress', value } / { type: 'done', buffer, duration, width, height }
 * / { type: 'error', message }.
 */

const MAX_SHORT_SIDE = 1080;

self.onmessage = async ({ data }) => {
	try {
		const mb = await import(data.libraryUrl);

		const input = new mb.Input({ source: new mb.BlobSource(data.file), formats: mb.ALL_FORMATS });
		const track = await input.getPrimaryVideoTrack();
		if (!track) {
			throw new Error('No video found in this file.');
		}

		const width = track.displayWidth;
		const height = track.displayHeight;
		const scale = Math.min(width, height) > MAX_SHORT_SIDE;
		const size = !scale ? {} : width >= height ? { height: MAX_SHORT_SIDE } : { width: MAX_SHORT_SIDE };

		const output = new mb.Output({
			format: new mb.Mp4OutputFormat({ fastStart: 'in-memory' }),
			target: new mb.BufferTarget(),
		});

		const conversion = await mb.Conversion.init({
			input,
			output,
			video: Object.assign({ codec: 'avc', quality: mb.QUALITY_HIGH }, size),
			audio: { codec: 'aac' },
		});

		// A dropped audio track would still produce a "valid" (silent) video.
		const dropped = conversion.discardedTracks.filter((t) => t.reason !== 'max_track_count_of_type_reached');
		if (!conversion.isValid || dropped.length) {
			const reasons = dropped.map((t) => t.track.type + ': ' + t.reason).join(', ');
			throw new Error('This browser cannot convert this video (' + reasons + '). Try Chrome or Edge.');
		}

		conversion.onProgress = (value) => self.postMessage({ type: 'progress', value });
		await conversion.execute();

		const buffer = output.target.buffer;
		const duration = await input.computeDuration();
		const outWidth = scale ? (width >= height ? Math.round((width * MAX_SHORT_SIDE) / height) : MAX_SHORT_SIDE) : width;
		const outHeight = scale ? (width >= height ? MAX_SHORT_SIDE : Math.round((height * MAX_SHORT_SIDE) / width)) : height;

		self.postMessage({ type: 'done', buffer, duration, width: outWidth, height: outHeight }, [buffer]);
	} catch (e) {
		self.postMessage({ type: 'error', message: e && e.message ? e.message : String(e) });
	}
};
