<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class VideoMergeController extends Controller
{
    /**
     * Temp directory for downloaded files
     */
    private string $tempDir;

    /**
     * Output directory for merged videos
     */
    private string $outputDir;

    /**
     * Path to FFmpeg binary
     */
    private string $ffmpegPath;

    public function __construct()
    {
        $this->tempDir = storage_path('app/video_merge');
        $this->outputDir = public_path('merged_videos');
        $this->ffmpegPath = config('app.ffmpeg_path', '/home/thuongnm/bin/ffmpeg');
    }

    /**
     * Merge video with TTS audio
     *
     * POST /api/video/merge
     */
    public function merge(Request $request)
    {
        // Set execution timeout for long FFmpeg operations
        set_time_limit(300);

        // Validate request
        $validated = $request->validate([
            'video_url' => 'required|url',
            'audio_url' => 'required|url',
            'job_id' => 'required|string|max:100|regex:/^[a-zA-Z0-9_-]+$/',
        ]);

        $jobId = $validated['job_id'];
        $videoUrl = $validated['video_url'];
        $audioUrl = $validated['audio_url'];

        // Ensure directories exist
        $this->ensureDirectoriesExist();

        // Define file paths
        $videoPath = "{$this->tempDir}/{$jobId}_video.mp4";
        $audioPath = "{$this->tempDir}/{$jobId}_audio.mp3";
        $outputPath = "{$this->outputDir}/{$jobId}_final.mp4";

        try {
            // Download video
            Log::info("[VideoMerge] Job {$jobId}: Downloading video from {$videoUrl}");
            if (!$this->downloadFile($videoUrl, $videoPath)) {
                throw new \Exception("Failed to download video from: {$videoUrl}");
            }

            // Download audio
            Log::info("[VideoMerge] Job {$jobId}: Downloading audio from {$audioUrl}");
            if (!$this->downloadFile($audioUrl, $audioPath)) {
                throw new \Exception("Failed to download audio from: {$audioUrl}");
            }

            // Verify downloads
            if (!file_exists($videoPath) || filesize($videoPath) === 0) {
                throw new \Exception("Video file is empty or not downloaded");
            }
            if (!file_exists($audioPath) || filesize($audioPath) === 0) {
                throw new \Exception("Audio file is empty or not downloaded");
            }

            Log::info("[VideoMerge] Job {$jobId}: Starting FFmpeg merge");

            // Run FFmpeg merge
            $ffmpegResult = $this->runFfmpegMerge($videoPath, $audioPath, $outputPath, $jobId);

            if (!$ffmpegResult['success']) {
                throw new \Exception("FFmpeg failed: " . $ffmpegResult['error']);
            }

            // Verify output
            if (!file_exists($outputPath) || filesize($outputPath) === 0) {
                throw new \Exception("Output file was not created or is empty");
            }

            // Cleanup temp files
            $this->cleanupTempFiles($jobId);

            Log::info("[VideoMerge] Job {$jobId}: Merge completed successfully");

            // Return success response
            return response()->json([
                'success' => true,
                'output_url' => url("merged_videos/{$jobId}_final.mp4"),
                'job_id' => $jobId,
                'file_size' => filesize($outputPath),
            ]);

        } catch (\Exception $e) {
            Log::error("[VideoMerge] Job {$jobId}: Error - " . $e->getMessage());

            // Cleanup on error
            $this->cleanupTempFiles($jobId);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'job_id' => $jobId,
            ], 500);
        }
    }

    /**
     * Cleanup merged video file
     *
     * POST /api/video/cleanup
     */
    public function cleanup(Request $request)
    {
        $validated = $request->validate([
            'job_id' => 'required|string|max:100|regex:/^[a-zA-Z0-9_-]+$/',
        ]);

        $jobId = $validated['job_id'];
        $outputPath = "{$this->outputDir}/{$jobId}_final.mp4";

        if (file_exists($outputPath)) {
            unlink($outputPath);
            Log::info("[VideoMerge] Job {$jobId}: Cleaned up output file");

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully',
                'job_id' => $jobId,
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => 'File not found',
            'job_id' => $jobId,
        ], 404);
    }

    /**
     * Ensure temp and output directories exist
     */
    private function ensureDirectoriesExist(): void
    {
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    /**
     * Download file from URL
     */
    private function downloadFile(string $url, string $destination): bool
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 120,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'follow_location' => true,
                'max_redirects' => 5,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        try {
            $content = @file_get_contents($url, false, $context);

            if ($content === false) {
                // Try with curl as fallback
                return $this->downloadWithCurl($url, $destination);
            }

            return file_put_contents($destination, $content) !== false;
        } catch (\Exception $e) {
            Log::error("[VideoMerge] Download error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Download file using curl (fallback)
     */
    private function downloadWithCurl(string $url, string $destination): bool
    {
        $ch = curl_init($url);
        $fp = fopen($destination, 'wb');

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);
        fclose($fp);

        if (!$result || $httpCode >= 400) {
            Log::error("[VideoMerge] Curl download failed. HTTP: {$httpCode}, Error: {$error}");
            return false;
        }

        return true;
    }

    /**
     * Run FFmpeg to merge video with TTS audio
     *
     * Formula:
     * - Original audio: 30% volume
     * - TTS audio: 100% volume
     * - Duration: first (match video duration)
     */
    private function runFfmpegMerge(string $videoPath, string $audioPath, string $outputPath, string $jobId): array
    {
        // FFmpeg command:
        // - Input 0: Video file (with original audio)
        // - Input 1: TTS audio file
        // - Filter: Mix audio with different volumes, duration=first
        // - Output: Copy video stream, encode mixed audio
        $command = sprintf(
            '%s -y -i %s -i %s ' .
            '-filter_complex "[0:a]volume=0.3[a0];[1:a]volume=1.0[a1];[a0][a1]amix=inputs=2:duration=first:dropout_transition=2[aout]" ' .
            '-map 0:v -map "[aout]" ' .
            '-c:v copy -c:a aac -b:a 192k ' .
            '-movflags +faststart ' .
            '%s 2>&1',
            escapeshellarg($this->ffmpegPath),
            escapeshellarg($videoPath),
            escapeshellarg($audioPath),
            escapeshellarg($outputPath)
        );

        Log::info("[VideoMerge] Job {$jobId}: FFmpeg command: {$command}");

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        $outputLog = implode("\n", $output);
        Log::info("[VideoMerge] Job {$jobId}: FFmpeg output:\n{$outputLog}");

        if ($returnCode !== 0) {
            return [
                'success' => false,
                'error' => $outputLog,
                'return_code' => $returnCode,
            ];
        }

        return [
            'success' => true,
            'output' => $outputLog,
        ];
    }

    /**
     * Cleanup temporary files for a job
     */
    private function cleanupTempFiles(string $jobId): void
    {
        $files = [
            "{$this->tempDir}/{$jobId}_video.mp4",
            "{$this->tempDir}/{$jobId}_audio.mp3",
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Check system status (FFmpeg, disk space, etc.)
     *
     * GET /api/video/status
     */
    public function status()
    {
        // Check FFmpeg
        $ffmpegVersion = shell_exec(escapeshellarg($this->ffmpegPath) . ' -version 2>&1 | head -1');
        $ffmpegInstalled = $ffmpegVersion && strpos($ffmpegVersion, 'ffmpeg version') !== false;

        // Check disk space
        $diskFree = disk_free_space($this->outputDir ?: '/');
        $diskTotal = disk_total_space($this->outputDir ?: '/');

        // Count pending files
        $pendingFiles = is_dir($this->outputDir)
            ? count(glob("{$this->outputDir}/*.mp4"))
            : 0;

        return response()->json([
            'success' => true,
            'ffmpeg_installed' => $ffmpegInstalled,
            'ffmpeg_path' => $this->ffmpegPath,
            'ffmpeg_version' => trim($ffmpegVersion ?? ''),
            'disk_free_gb' => round($diskFree / 1024 / 1024 / 1024, 2),
            'disk_total_gb' => round($diskTotal / 1024 / 1024 / 1024, 2),
            'pending_files' => $pendingFiles,
            'temp_dir' => $this->tempDir,
            'output_dir' => $this->outputDir,
        ]);
    }
}
