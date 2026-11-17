import { Moon, Sun } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useTheme, type Theme } from './ThemeContext'

/** The light / dark / system choice, for any dropdown menu. */
export function ThemeRadioGroup() {
  const { theme, setTheme } = useTheme()

  return (
    <DropdownMenuRadioGroup value={theme} onValueChange={(value) => setTheme(value as Theme)}>
      <DropdownMenuRadioItem value="light">Jasny</DropdownMenuRadioItem>
      <DropdownMenuRadioItem value="dark">Ciemny</DropdownMenuRadioItem>
      <DropdownMenuRadioItem value="system">Systemowy</DropdownMenuRadioItem>
    </DropdownMenuRadioGroup>
  )
}

export function ModeToggle() {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" size="icon">
          <Sun className="scale-100 rotate-0 transition-all dark:scale-0 dark:-rotate-90" />
          <Moon className="absolute scale-0 rotate-90 transition-all dark:scale-100 dark:rotate-0" />
          <span className="sr-only">Motyw</span>
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end">
        <ThemeRadioGroup />
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
