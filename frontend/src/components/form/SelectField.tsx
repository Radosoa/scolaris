import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

export interface Option {
  value: number
  label: string
}

interface SelectFieldProps {
  id: string
  value: number | undefined
  onChange: (valeur: number) => void
  options: Option[]
  placeholder?: string
  invalid?: boolean
  disabled?: boolean
}

/** Select shadcn travaillant avec des identifiants numériques (clés étrangères). */
export function SelectField({ id, value, onChange, options, placeholder = 'Choisir…', invalid, disabled }: SelectFieldProps) {
  return (
    <Select
      value={value ? String(value) : ''}
      onValueChange={(valeur) => onChange(Number(valeur))}
      disabled={disabled}
    >
      <SelectTrigger id={id} aria-invalid={invalid} className="w-full">
        <SelectValue placeholder={placeholder} />
      </SelectTrigger>
      <SelectContent>
        {options.map((option) => (
          <SelectItem key={option.value} value={String(option.value)}>
            {option.label}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
