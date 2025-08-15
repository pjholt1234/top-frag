import { Button } from '@/components/ui/button';

export default function ExampleComponent() {
  return (
    <div className="p-8 space-y-4">
      <h1 className="text-3xl font-bold">Welcome to shadcn/ui in Laravel!</h1>
      <p className="text-muted-foreground">
        This is an example component showing how to use shadcn/ui components in
        your Laravel React application.
      </p>

      <div className="flex gap-4">
        <Button variant="default">Default Button</Button>
        <Button variant="secondary">Secondary Button</Button>
        <Button variant="destructive">Destructive Button</Button>
        <Button variant="outline">Outline Button</Button>
        <Button variant="ghost">Ghost Button</Button>
        <Button variant="link">Link Button</Button>
      </div>
    </div>
  );
}
