import type { FormAnswer as QueryFormAnswer, Option as QueryOption, Question as QueryQuestion, SelectedOption } from '../../../shared/types/query-form';
import type { SurveyQuestion, SurveyQuestionOption } from '../../../shared/types/survey';

// Unified Option type that works with both query-form and survey options
export interface Option {
    id: number;
    value: string;
    label: string;
    text?: string; // For survey options compatibility
    order_index?: number;
    allows_free_text?: boolean;
}

// Unified Question type that works with both query-form and survey questions
export interface Question {
    id: number;
    code: string;
    text: string;
    type: string;
    is_required: boolean;
    help_text: string | null;
    order_index?: number;
    options: Option[];
    validation_json?: Record<string, unknown> | unknown | null;
    visibility_condition_json?: Record<string, unknown> | null;
}

// Re-export FormAnswer and SelectedOption from query-form
export type FormAnswer = QueryFormAnswer;
export type { SelectedOption };

export interface FormAnswerState {
    [questionId: number]: FormAnswer;
}

export interface QuestionProps {
    question: Question;
    modelValue: FormAnswer | undefined;
    readonly?: boolean;
}

export interface QuestionEmits {
    (e: 'update:modelValue', value: FormAnswer): void;
}

// Helper functions for form answers
export function normalizeToString(value: string | number | null | undefined): string {
    if (value === null || value === undefined) return '';
    return String(value);
}

export function sortOptions(options?: Option[] | SurveyQuestionOption[] | QueryOption[]): Option[] {
    const opts = [...(options ?? [])] as Option[];
    return opts.sort((a, b) => (a.order_index ?? 0) - (b.order_index ?? 0));
}

export function sortQuestions(questions?: Question[] | SurveyQuestion[] | QueryQuestion[]): Question[] {
    const qs = [...(questions ?? [])] as Question[];
    return qs.sort((a, b) => (a.order_index ?? 0) - (b.order_index ?? 0));
}

// Convert SurveyQuestion to unified Question format
export function normalizeQuestion(question: SurveyQuestion | QueryQuestion | Question): Question {
    return {
        id: question.id,
        code: question.code,
        text: question.text,
        type: question.type,
        is_required: question.is_required,
        help_text: question.help_text,
        order_index: question.order_index,
        options: (question.options ?? []).map((opt) => normalizeOption(opt)),
        validation_json: question.validation_json ?? null,
        visibility_condition_json: (question as QueryQuestion).visibility_condition_json ?? null,
    };
}

// Convert SurveyQuestionOption to unified Option format
export function normalizeOption(option: SurveyQuestionOption | QueryOption | Option): Option {
    return {
        id: option.id,
        value: option.value,
        label: (option as QueryOption).label ?? (option as SurveyQuestionOption).text ?? option.value,
        text: (option as SurveyQuestionOption).text,
        order_index: option.order_index ?? 0,
        allows_free_text: option.allows_free_text ?? false,
    };
}
export interface FormSection {
    id: number;
    title: string;
    description: string | null;
    order_index: number;
    questions: Question[];
}
