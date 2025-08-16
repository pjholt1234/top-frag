import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from '@/components/ui/sheet';
import { Upload, File, AlertCircle, CheckCircle } from 'lucide-react';
import { api } from '@/lib/api';

interface UploadDemoModalProps {
  onUploadSuccess?: () => void;
}

export function UploadDemoModal({ onUploadSuccess }: UploadDemoModalProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [file, setFile] = useState<File | null>(null);
  const [isUploading, setIsUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

  const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFile = event.target.files?.[0];
    if (selectedFile) {
      // Validate file type - check for .dem extension (case insensitive)
      const fileName = selectedFile.name.toLowerCase();
      if (!fileName.endsWith('.dem')) {
        setError('Please select a valid .dem file');
        setFile(null);
        return;
      }

      // Validate file size (1GB max)
      const maxSize = 1024 * 1024 * 1024; // 1GB in bytes
      if (selectedFile.size > maxSize) {
        setError('File size must be less than 1GB');
        setFile(null);
        return;
      }

      setFile(selectedFile);
      setError(null);
    }
  };

  const handleUpload = async () => {
    if (!file) return;

    setIsUploading(true);
    setError(null);
    setSuccess(false);

    try {
      const formData = new FormData();
      formData.append('demo', file);

      // Use the authenticated endpoint for upload
      const response = await api.post('/user/upload/demo', formData, {
        requireAuth: true,
        headers: {
          // Remove Content-Type header to let browser set it with boundary for FormData
        },
      });

      if (response.data && typeof response.data === 'object' && 'success' in response.data && response.data.success) {
        setSuccess(true);
        setFile(null);
        // Reset file input
        const fileInput = document.getElementById(
          'demo-file'
        ) as HTMLInputElement;
        if (fileInput) {
          fileInput.value = '';
        }

        // Close modal after a short delay
        setTimeout(() => {
          setIsOpen(false);
          setSuccess(false);
          if (onUploadSuccess) {
            onUploadSuccess();
          }
        }, 2000);
      }
    } catch (err: unknown) {
      console.error('Upload error:', err);
      const errorMessage = err instanceof Error ? err.message : 'Failed to upload demo file';
      setError(errorMessage);
    } finally {
      setIsUploading(false);
    }
  };

  const handleOpenChange = (open: boolean) => {
    setIsOpen(open);
    if (!open) {
      // Reset state when closing
      setFile(null);
      setError(null);
      setSuccess(false);
      const fileInput = document.getElementById(
        'demo-file'
      ) as HTMLInputElement;
      if (fileInput) {
        fileInput.value = '';
      }
    }
  };

  return (
    <Sheet open={isOpen} onOpenChange={handleOpenChange}>
      <SheetTrigger asChild>
        <Button
          size="sm"
          className="border bg-background shadow-xs hover:bg-accent hover:text-accent-foreground border-custom-orange text-white"
        >
          <Upload className="h-4 w-4 mr-2" />
          Upload Demo
        </Button>
      </SheetTrigger>
      <SheetContent>
        <SheetHeader>
          <SheetTitle>Upload Demo File</SheetTitle>
          <SheetDescription>
            Upload a CS:GO demo file (.dem) to analyze your matches and view
            detailed statistics.
          </SheetDescription>
        </SheetHeader>

        <div className="space-y-6 py-6 px-4">
          {error && (
            <Alert variant="destructive">
              <AlertCircle className="h-4 w-4" />
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}

          {success && (
            <Alert>
              <CheckCircle className="h-4 w-4" />
              <AlertDescription>
                Demo uploaded successfully! Processing will begin shortly.
              </AlertDescription>
            </Alert>
          )}

          <div className="space-y-2">
            <Label htmlFor="demo-file">Demo File</Label>
            <Input
              id="demo-file"
              type="file"
              onChange={handleFileChange}
              disabled={isUploading}
            />
            <p className="text-xs text-muted-foreground">
              Select a .dem file (max 1GB)
            </p>
          </div>

          {file && (
            <div className="flex items-center space-x-2 p-3 bg-muted rounded-md">
              <File className="h-4 w-4" />
              <span className="text-sm font-medium">{file.name}</span>
              <span className="text-xs text-muted-foreground">
                ({(file.size / 1024 / 1024).toFixed(2)} MB)
              </span>
            </div>
          )}
        </div>

        <SheetFooter>
          <Button
            onClick={handleUpload}
            disabled={!file || isUploading}
            className="w-full bg-custom-orange"
          >
            {isUploading ? (
              <>
                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2" />
                Uploading...
              </>
            ) : (
              <>
                <Upload className="h-4 w-4 mr-2" />
                Upload Demo
              </>
            )}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
